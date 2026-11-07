<?php

namespace App\Simulation;

use App\Models\Address;
use App\Models\Developer;
use App\Models\SimulationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

// M4 simulator (SIM-02..08, blueprint §9.3). Turns biodata rows into
// real sim buyer accounts: one User (role pembeli,
// origin=developer_simulation, sim-domain email, active) plus one
// Address, inside ONE DB transaction. One action = one run (SIM-02):
// this service opens the run itself, because the effective seed is
// only known AFTER generation — in random mode the generator picks
// the seed (validator: "seed null → the generator picks one"), and
// simulation_runs.seed is NOT NULL, so the run cannot be created
// before the generator has run. The controller stays thin: validate
// the input, call this service, show the run.
final class SimulationAccountService
{
    private const MAX_GENERATION_ATTEMPTS = 3;

    public function __construct(
        private SimulationRequestValidator $validator,
        private SimulationAudit $audit,
    ) {}

    /**
     * $input is the validateAccountCreation shape:
     * ['count' => int, 'mode' => 'random'|'deterministic',
     *  'seed' => ?int, 'offset' => int].
     *
     * @return array{users: list<User>, run: SimulationRun}
     */
    public function createAccounts(
        Developer $developer,
        BiodataGenerator $generator,
        array $input,
        string $idempotencyKey,
    ): array {
        $count = (int) ($input['count'] ?? 0);
        $mode = (string) ($input['mode'] ?? '');
        $seed = $input['seed'] ?? null;
        $offset = (int) ($input['offset'] ?? 0);

        if ($count < 1 || $count > 100 || $mode === '') {
            throw new InvalidArgumentException('Input pembuatan akun simulasi tidak valid.');
        }

        // SIM-08: refuse outright when the shared password is unset.
        $this->validator->validateAccountPassword();

        // SIM-26: check for an existing run with this key BEFORE
        // generating — generateBiodata() rejects on email collision,
        // so a repeated submit would fail there and never reach the
        // replay branch below. Only the user-controlled params are
        // compared: in random mode the seed is picked by the
        // generator, so it is not compared here.
        $existing = SimulationRun::query()
            ->where('developer_id', $developer->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            $seedMatches = $mode !== 'deterministic'
                || ($seed !== null && (int) ($existing->params['seed'] ?? -1) === (int) $seed);

            $paramsMatch = (int) ($existing->params['count'] ?? -1) === $count
                && ($existing->params['mode'] ?? null) === $mode
                && (int) ($existing->params['offset'] ?? -1) === $offset
                && $seedMatches;

            if (! $paramsMatch) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'Kunci ini sudah dipakai untuk parameter lain. Buat pratinjau baru.',
                ]);
            }

            if ($existing->status === 'berhasil') {
                return ['users' => $this->usersOfRun($existing), 'run' => $existing];
            }

            throw ValidationException::withMessages([
                'idempotency_key' => 'Run dengan kunci ini sudah ada (status: '.$existing->status.'). Buat pratinjau baru untuk mengulang.',
            ]);
        }

        // Generate first — the run's seed comes from here (random mode:
        // the generator picks it; deterministic: it is the given one).
        $biodata = $this->generateBiodata($generator, $count, $offset, $seed, $mode);
        $effectiveSeed = $generator instanceof PythonBiodataGenerator || $generator instanceof SequentialBiodataGenerator
            ? $generator->lastSeed()
            : $seed;

        if ($effectiveSeed === null) {
            throw new InvalidArgumentException('Generator tidak melaporkan seed efektif; run tidak dapat dibuat.');
        }

        $params = ['count' => $count, 'mode' => $mode, 'seed' => $effectiveSeed, 'offset' => $offset];

        $run = $this->audit->recordRunStart(
            $developer,
            'create_accounts',
            $params,
            $effectiveSeed,
            $idempotencyKey,
        );

        // SIM-26: a repeated submit with the same key replays the
        // finished run instead of creating the accounts twice — but
        // only when the params match; the same key with different
        // params means the preview changed and must be re-made.
        if (! $run->wasRecentlyCreated) {
            if ($run->params != $params) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'Kunci ini sudah dipakai untuk parameter lain. Buat pratinjau baru.',
                ]);
            }

            if ($run->status === 'berhasil') {
                return ['users' => $this->usersOfRun($run), 'run' => $run];
            }

            throw ValidationException::withMessages([
                'idempotency_key' => 'Run dengan kunci ini sudah ada (status: '.$run->status.'). Buat pratinjau baru untuk mengulang.',
            ]);
        }

        try {
            $users = DB::transaction(fn () => $this->createUsers($biodata, $run));
        } catch (Throwable $e) {
            // Any failure inside the transaction rolls everything back
            // and fails the run — a non-validation failure (e.g. a DB
            // constraint) must not leave the run stuck 'running' (R9).
            $message = $e instanceof ValidationException
                ? $this->firstValidationMessage($e)
                : $e->getMessage();

            $this->audit->recordRunFailed($run, $message);

            throw $e;
        }

        $this->audit->recordRunFinish($run, ['accounts' => count($users)]);

        return ['users' => $users, 'run' => $run];
    }

    /** Users created by a run, oldest first (SIM-26 replay). */
    private function usersOfRun(SimulationRun $run): array
    {
        return User::query()
            ->where('simulation_run_id', $run->id)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * Deterministic (SIM-05): one generation; any collision rejects
     * the whole request. Random (SIM-04): up to 3 generations — each
     * call with a null seed yields a fresh random batch — then reject.
     */
    private function generateBiodata(
        BiodataGenerator $generator,
        int $count,
        int $offset,
        ?int $seed,
        string $mode,
    ): array {
        $attempts = $mode === 'random' ? self::MAX_GENERATION_ATTEMPTS : 1;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $biodata = $generator->generate($count, $offset, $seed);

            try {
                $this->validator->validateBiodataEmails($biodata);

                return $biodata;
            } catch (ValidationException $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?? new InvalidArgumentException('Generator biodata tidak mengembalikan data.');
    }

    /**
     * @param  list<array{name:string, email:string, username:?string, phone:?string, gender:?string, birthdate:?string, address:array}>  $biodata
     * @return list<User>
     */
    private function createUsers(array $biodata, SimulationRun $run): array
    {
        $password = (string) config('simulation.account_password');

        if ($password === '') {
            throw ValidationException::withMessages([
                'password' => 'SIM_ACCOUNT_PASSWORD belum diisi di .env — pembuatan akun simulasi ditolak (SIM-08).',
            ]);
        }

        $users = [];

        foreach ($biodata as $row) {
            // Hash computed once per account (blueprint §9.3), following
            // ProvisionAccountCommand: a pre-made hash is passed to the
            // model, and the 'hashed' cast does not re-hash it. The
            // password value itself is never logged or audited (SIM-08).
            $user = User::query()->create([
                'name' => $row['name'],
                'email' => $row['email'],
                'password' => Hash::make($password),
                'role' => 'pembeli',
                'origin' => 'developer_simulation',
                'is_active' => true,
                'simulation_run_id' => $run->id,
                'username' => $row['username'],
                'phone' => $row['phone'],
                'gender' => $row['gender'],
                'birthdate' => $row['birthdate'],
            ]);

            Address::query()->create([
                'user_id' => $user->id,
                'recipient_name' => $row['address']['recipient_name'],
                'recipient_phone' => $row['address']['recipient_phone'],
                'province' => $row['address']['province'],
                'city' => $row['address']['city'],
                'district' => $row['address']['district'],
                'village' => $row['address']['village'],
                'postal_code' => $row['address']['postal_code'],
                'street_address' => $row['address']['street_address'],
                'address_type' => $row['address']['address_type'],
            ]);

            $users[] = $user;
        }

        return $users;
    }

    private function firstValidationMessage(ValidationException $e): string
    {
        $messages = $e->errors();

        foreach ($messages as $fieldMessages) {
            foreach ((array) $fieldMessages as $message) {
                return (string) $message;
            }
        }

        return 'Pembuatan akun simulasi gagal.';
    }
}
