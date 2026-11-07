<?php

namespace App\Simulation;

use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

// M4 simulator (SIM-03/05/08/09/11/17/19/22/23/28/31, blueprint §9.1).
// Server-side gate for every simulator request: validates limits, user
// scope, products, period and email collisions, and returns normalized
// planner params (the shape FunnelPlanner::plan / planFunnel expects).
// Throws ValidationException with Indonesian messages; the controller /
// FormRequest layer surfaces them as form errors.
//
// Reads config('simulation.*') with the documented defaults as fallback.
// The 100-account cap is enforced in code and can never be configured
// above 100 (SIM-03).
final class SimulationRequestValidator
{
    // ---- Aksi 1: buat akun (SIM-02/03/05/08) ---------------------------

    /**
     * @return array{count:int, mode:string, seed:?int, offset:int}
     *   Seed is null in random mode (the generator picks one); in
     *   deterministic mode it defaults to config default_seed (U-02).
     */
    public function validateAccountCreation(array $input): array
    {
        $count = $this->toInt($input['count'] ?? null);

        if ($count === null || $count < 1 || $count > $this->maxAccounts()) {
            throw ValidationException::withMessages([
                'count' => "Jumlah akun harus bilangan bulat 1–{$this->maxAccounts()}. Jumlah lebih besar harus dipecah menjadi beberapa request (SIM-03).",
            ]);
        }

        $mode = (string) ($input['mode'] ?? $this->defaultMode());

        if (! in_array($mode, ['random', 'deterministic'], true)) {
            throw ValidationException::withMessages(['mode' => "Mode biodata harus 'random' atau 'deterministic'."]);
        }

        $seed = $this->optionalSeed($input, $mode);

        $offset = $this->toInt($input['offset'] ?? 0);

        if ($offset === null || $offset < 0) {
            throw ValidationException::withMessages(['offset' => 'Offset harus bilangan bulat ≥ 0.']);
        }

        return ['count' => $count, 'mode' => $mode, 'seed' => $seed, 'offset' => $offset];
    }

    /** SIM-08: the shared account password must be configured. */
    public function validateAccountPassword(): void
    {
        if (blank(config('simulation.account_password'))) {
            throw ValidationException::withMessages([
                'sim_account_password' => 'SIM_ACCOUNT_PASSWORD belum diisi di .env — pembuatan akun simulasi ditolak (SIM-08).',
            ]);
        }
    }

    /**
     * SIM-05 / blueprint §9.3: after generation, check the biodata
     * emails against the sim domain, within the batch, and against
     * existing users. Any collision rejects the whole request.
     *
     * @param list<array{email?:string}> $biodata
     */
    public function validateBiodataEmails(array $biodata): void
    {
        $domain = mb_strtolower($this->simDomain());
        $seen = [];
        $emails = [];

        foreach ($biodata as $index => $row) {
            $email = isset($row['email']) ? mb_strtolower(trim((string) $row['email'])) : '';

            if ($email === '' || ! str_ends_with($email, '@'.$domain)) {
                throw ValidationException::withMessages([
                    'email' => 'Biodata #'.($index + 1)." tidak memiliki email pada domain simulasi (@{$domain}).",
                ]);
            }

            if (isset($seen[$email])) {
                throw ValidationException::withMessages([
                    'email' => "Email '{$email}' muncul lebih dari sekali di hasil generator.",
                ]);
            }

            $seen[$email] = true;
            $emails[] = $email;
        }

        if ($emails === []) {
            return;
        }

        // Users store lowercased emails (User mutator), so a plain
        // whereIn on the lowercased batch is an exact match.
        $colliding = User::query()->whereIn('email', $emails)->pluck('email')->all();

        if ($colliding !== []) {
            $shown = implode(', ', array_slice($colliding, 0, 3)).(count($colliding) > 3 ? ', …' : '');

            throw ValidationException::withMessages([
                'email' => "Biodata bertabrakan dengan akun yang sudah ada ({$shown}). Coba seed/offset lain atau reset data simulasi (SIM-05).",
            ]);
        }
    }

    // ---- Periode (SIM-28) ------------------------------------------------

    public function validatePeriod(string $start, string $end): PeriodRange
    {
        try {
            return PeriodResolver::fromConfig()->resolve($start, $end);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['period' => $e->getMessage()]);
        }
    }

    // ---- Cakupan user (SIM-09/18/19) -------------------------------------

    /**
     * Validate an explicit id list: non-empty, ≤ 100, every id a
     * simulation account (a normal id is rejected even if sent manually).
     * Inactive simulation accounts are SKIPPED, not rejected (SIM-19);
     * the skip is reported so the preview can show it.
     *
     * @return array{user_ids: list<int>, skipped_inactive_ids: list<int>}
     */
    public function validateUserScope(array $userIds): array
    {
        $ids = [];

        foreach ($userIds as $id) {
            $int = $this->toInt($id);

            if ($int !== null) {
                $ids[] = $int;
            }
        }

        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            throw ValidationException::withMessages(['user_ids' => 'Pilih minimal 1 akun simulasi (SIM-09).']);
        }

        if (count($ids) > $this->maxAccounts()) {
            throw ValidationException::withMessages(['user_ids' => "Maksimal {$this->maxAccounts()} akun per request (SIM-09)."]);
        }

        $users = User::query()->whereIn('id', $ids)->get(['id', 'origin', 'is_active'])->keyBy('id');

        foreach ($ids as $id) {
            if (! $users->has($id)) {
                throw ValidationException::withMessages(['user_ids' => "Akun tidak ditemukan (id: {$id})."]);
            }

            if ($users->get($id)->origin !== 'developer_simulation') {
                throw ValidationException::withMessages(['user_ids' => 'Hanya akun simulasi yang dapat dipilih; akun normal ditolak (SIM-09).']);
            }
        }

        $active = [];
        $skipped = [];

        foreach ($users as $user) {
            if ($user->is_active) {
                $active[] = (int) $user->id;
            } else {
                $skipped[] = (int) $user->id;
            }
        }

        sort($active);
        sort($skipped);

        if ($active === []) {
            throw ValidationException::withMessages(['user_ids' => 'Tidak ada akun simulasi aktif — buat akun lebih dulu atau aktifkan akun yang ada (SIM-19).']);
        }

        return ['user_ids' => $active, 'skipped_inactive_ids' => $skipped];
    }

    /**
     * SIM-18 user scope: 'one' (exactly 1 selected), 'several' (≥ 1
     * selected), 'all' (every simulation account), 'random' (k seeded
     * picks). Returns the validated scope plus the effective seed (the
     * passed seed, or a generated one for random scope).
     *
     * @return array{user_ids: list<int>, skipped_inactive_ids: list<int>, seed: ?int}
     */
    public function resolveUserScope(string $scope, array $selectedIds = [], ?int $randomK = null, ?int $seed = null): array
    {
        $allSimIds = User::query()
            ->where('origin', 'developer_simulation')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($allSimIds === []) {
            throw ValidationException::withMessages(['user_scope' => 'Belum ada akun simulasi — buat akun lebih dulu (SIM-19).']);
        }

        if ($scope === 'one' && count($selectedIds) !== 1) {
            throw ValidationException::withMessages(['user_scope' => "Cakupan 'satu user' membutuhkan tepat 1 akun."]);
        }

        if ($scope === 'random') {
            if ($randomK === null || $randomK < 1) {
                throw ValidationException::withMessages(['random_k' => 'Jumlah user acak (k) harus bilangan bulat ≥ 1.']);
            }

            if ($randomK > count($allSimIds)) {
                throw ValidationException::withMessages(['random_k' => "k ({$randomK}) melebihi jumlah akun simulasi tersedia (".count($allSimIds).') (SIM-19).']);
            }

            // The seed is generated here when absent so the run stores
            // the exact seed that picked the users (blueprint §9.2).
            $seed ??= random_int(1, PHP_INT_MAX);
            $candidates = array_slice((new SeededRandom($seed))->shuffle($allSimIds), 0, $randomK);
        } elseif ($scope === 'all') {
            $candidates = $allSimIds;
        } elseif ($scope === 'one' || $scope === 'several') {
            $candidates = $selectedIds;
        } else {
            throw ValidationException::withMessages(['user_scope' => "Cakupan user tidak dikenal: '{$scope}'."]);
        }

        // Random picks may include inactive accounts; they are skipped
        // (and reported) by validateUserScope, per SIM-19.
        $resolved = $this->validateUserScope($candidates);
        $resolved['seed'] = $seed;

        return $resolved;
    }

    // ---- Produk (SIM-11/22) ----------------------------------------------

    /**
     * Empty selection → all active products. A non-empty selection must
     * exist and be active. Returns [['id', 'price_idr']] sorted by id.
     *
     * @return list<array{id:int, price_idr:int}>
     */
    public function validateProductScope(array $productIds = []): array
    {
        $ids = null;

        if ($productIds !== []) {
            $ids = [];

            foreach ($productIds as $id) {
                $int = $this->toInt($id);

                if ($int !== null) {
                    $ids[] = $int;
                }
            }

            $ids = array_values(array_unique($ids));

            if ($ids === []) {
                throw ValidationException::withMessages(['product_ids' => 'Pilihan produk tidak valid.']);
            }

            $existing = Product::query()->whereIn('id', $ids)->pluck('is_active', 'id');

            foreach ($ids as $id) {
                if (! $existing->has($id)) {
                    throw ValidationException::withMessages(['product_ids' => "Produk tidak ditemukan (id: {$id})."]);
                }

                if (! $existing->get($id)) {
                    throw ValidationException::withMessages(['product_ids' => "Produk (id: {$id}) tidak aktif."]);
                }
            }
        }

        $products = Product::query()
            ->where('is_active', true)
            ->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))
            ->orderBy('id')
            ->get(['id', 'price_idr']);

        if ($products->isEmpty()) {
            throw ValidationException::withMessages(['product_ids' => 'Tidak ada produk aktif untuk simulasi (SIM-22).']);
        }

        return $products
            ->map(fn (Product $product): array => ['id' => (int) $product->id, 'price_idr' => (int) $product->price_idr])
            ->all();
    }

    // ---- Aksi 2: user-first (SIM-09/11/12) -------------------------------

    /**
     * Returns planner params for FunnelPlanner::plan() (action
     * 'tx_user_first'). Per-account transaction counts are exact: the
     * planner runs with user_distribution 'even' (round-robin), so N
     * accounts × k transactions each is exactly k per account (SIM-11).
     */
    public function validateUserFirst(array $input): array
    {
        $scope = $this->validateUserScope($input['user_ids'] ?? []);

        $perAccount = $this->toInt($input['transactions_per_account'] ?? null);

        if ($perAccount === null || $perAccount < 1) {
            throw ValidationException::withMessages(['transactions_per_account' => 'Jumlah transaksi per akun harus bilangan bulat ≥ 1 (SIM-11).']);
        }

        $total = count($scope['user_ids']) * $perAccount;

        if ($total > $this->maxTransactions()) {
            throw ValidationException::withMessages([
                'transactions_per_account' => "Total transaksi {$total} melebihi batas {$this->maxTransactions()} per request — kurangi akun atau transaksi per akun (SIM-17).",
            ]);
        }

        $period = $this->validatePeriod((string) ($input['period_start'] ?? ''), (string) ($input['period_end'] ?? ''));

        return array_merge($this->validateCommonTx($input, $period), [
            'action' => 'tx_user_first',
            'user_ids' => $scope['user_ids'],
            'skipped_inactive_ids' => $scope['skipped_inactive_ids'],
            'transaction_count' => $total,
            'transactions_per_account' => $perAccount,
            'user_distribution' => 'even',
        ]);
    }

    // ---- Aksi 3: period-first (SIM-16..24) -------------------------------

    /** Returns planner params for FunnelPlanner::plan() (action 'tx_period_first'). */
    public function validatePeriodFirst(array $input): array
    {
        // The plan seed is resolved first: a 'random' user scope spends
        // it to pick the users, so the run stores the seed that did so.
        $seed = $this->resolveSeed($input);

        $scope = $this->resolveUserScope(
            (string) ($input['user_scope'] ?? 'all'),
            $input['user_ids'] ?? [],
            $this->toInt($input['random_k'] ?? null),
            $seed,
        );

        $count = $this->toInt($input['transaction_count'] ?? null);

        if ($count === null || $count < 1 || $count > $this->maxTransactions()) {
            throw ValidationException::withMessages([
                'transaction_count' => "Jumlah transaksi harus bilangan bulat 1–{$this->maxTransactions()} (SIM-17).",
            ]);
        }

        $period = $this->validatePeriod((string) ($input['period_start'] ?? ''), (string) ($input['period_end'] ?? ''));

        return array_merge($this->validateCommonTx($input, $period, $seed), [
            'action' => 'tx_period_first',
            'user_ids' => $scope['user_ids'],
            'skipped_inactive_ids' => $scope['skipped_inactive_ids'],
            'user_scope' => (string) ($input['user_scope'] ?? 'all'),
            'transaction_count' => $count,
        ]);
    }

    // ---- Aksi 4: funnel eksplisit (SIM-31) -------------------------------

    /** Returns planner params for FunnelPlanner::planFunnel() (action 'funnel_events'). */
    public function validateFunnel(array $input): array
    {
        $scope = $this->validateUserScope($input['user_ids'] ?? []);
        $products = $this->validateProductScope($input['product_ids'] ?? []);
        $period = $this->validatePeriod((string) ($input['period_start'] ?? ''), (string) ($input['period_end'] ?? ''));

        $counts = [];

        foreach (['view_count', 'add_to_cart_count', 'booking_count', 'purchase_count'] as $key) {
            $value = $this->toInt($input[$key] ?? null);

            if ($value === null || $value < 0) {
                throw ValidationException::withMessages([$key => "'{$key}' harus bilangan bulat ≥ 0."]);
            }

            $counts[$key] = $value;
        }

        if (! ($counts['view_count'] >= $counts['add_to_cart_count']
            && $counts['add_to_cart_count'] >= $counts['booking_count']
            && $counts['booking_count'] >= $counts['purchase_count'])) {
            throw ValidationException::withMessages([
                'view_count' => 'Jumlah funnel harus menyusut: view ≥ add_to_cart ≥ booking ≥ purchase ≥ 0 (SIM-31).',
            ]);
        }

        if ($counts['booking_count'] < 1) {
            throw ValidationException::withMessages(['booking_count' => 'Aksi funnel membutuhkan minimal 1 booking.']);
        }

        // Total generated event rows: requested views + carts + 2 per
        // booking (booking + purchase/cancel; the chain view/cart are
        // already inside the requested counts).
        $totalEvents = $counts['view_count'] + $counts['add_to_cart_count'] + ($counts['booking_count'] * 2);

        if ($totalEvents > $this->maxEvents()) {
            throw ValidationException::withMessages([
                'view_count' => "Total event funnel {$totalEvents} melebihi batas {$this->maxEvents()} per request (SIM-31).",
            ]);
        }

        $seed = $this->resolveSeed($input);
        $timeDistribution = $this->validateEnum($input['time_distribution'] ?? 'random', ['random', 'even', 'single'], 'time_distribution');

        return array_merge($counts, [
            'action' => 'funnel_events',
            'user_ids' => $scope['user_ids'],
            'skipped_inactive_ids' => $scope['skipped_inactive_ids'],
            'products' => $products,
            'period_start' => $period->startDate,
            'period_end' => $period->endDate,
            'time_distribution' => $timeDistribution,
            'single_point' => $timeDistribution === 'single'
                ? $this->parseSinglePoint($input['single_point'] ?? null, $period)
                : null,
            'user_distribution' => $this->validateEnum($input['user_distribution'] ?? 'random', ['random', 'even', 'weighted'], 'user_distribution'),
            'product_distribution' => $this->validateEnum($input['product_distribution'] ?? 'random', ['random', 'even', 'weighted'], 'product_distribution'),
            'lag_minutes' => $this->lagMinutes(),
            'payment_methods' => $this->activePaymentMethods(),
            'payment_expiry_minutes' => $this->paymentExpiryMinutes(),
            'timezone' => (string) config('ira.reporting_timezone', 'Asia/Makassar'),
            'max_period_days' => (int) config('simulation.max_period_days', 366),
            'max_events_per_request' => $this->maxEvents(),
            'seed' => $seed,
            'mode' => (string) ($input['mode'] ?? $this->defaultMode()),
        ]);
    }

    // ---- Shared transaction params ---------------------------------------

    /**
     * Params shared by Aksi 2 and Aksi 3 (everything except the action,
     * user list and transaction count). A pre-resolved $seed may be
     * passed in (period-first resolves it first for the user scope).
     */
    private function validateCommonTx(array $input, PeriodRange $period, ?int $seed = null): array
    {
        $products = $this->validateProductScope($input['product_ids'] ?? []);

        $items = $this->validateRange($input['items_per_tx'] ?? null, 'items_per_tx', [1, 3], 1, null);

        if ($items[1] > count($products)) {
            throw ValidationException::withMessages([
                'items_per_tx' => "Jumlah item per transaksi (maks {$items[1]}) melebihi jumlah produk terpilih (".count($products).').',
            ]);
        }

        $qty = $this->validateRange($input['qty_per_item'] ?? null, 'qty_per_item', [1, 99], 1, 99);

        $seed ??= $this->resolveSeed($input);
        $timeDistribution = $this->validateEnum($input['time_distribution'] ?? 'random', ['random', 'even', 'single'], 'time_distribution');

        return [
            'products' => $products,
            'period_start' => $period->startDate,
            'period_end' => $period->endDate,
            'items_per_tx' => $items,
            'qty_per_item' => $qty,
            'time_distribution' => $timeDistribution,
            'single_point' => $timeDistribution === 'single'
                ? $this->parseSinglePoint($input['single_point'] ?? null, $period)
                : null,
            'user_distribution' => $this->validateEnum($input['user_distribution'] ?? 'random', ['random', 'even', 'weighted'], 'user_distribution'),
            'product_distribution' => $this->validateEnum($input['product_distribution'] ?? 'random', ['random', 'even', 'weighted'], 'product_distribution'),
            'status_mix' => $this->validateStatusMix($input['status_mix'] ?? null),
            'funnel_view_to_cart' => $this->validateRatio($input['funnel_view_to_cart'] ?? null, 'funnel_view_to_cart', (int) config('simulation.funnel_view_to_cart', 40)),
            'funnel_cart_to_booking' => $this->validateRatio($input['funnel_cart_to_booking'] ?? null, 'funnel_cart_to_booking', (int) config('simulation.funnel_cart_to_booking', 60)),
            'lag_minutes' => $this->lagMinutes(),
            'payment_methods' => $this->activePaymentMethods(),
            'payment_expiry_minutes' => $this->paymentExpiryMinutes(),
            'timezone' => (string) config('ira.reporting_timezone', 'Asia/Makassar'),
            'max_period_days' => (int) config('simulation.max_period_days', 366),
            'seed' => $seed,
            'mode' => (string) ($input['mode'] ?? $this->defaultMode()),
        ];
    }

    // ---- Small validators --------------------------------------------------

    /**
     * Resolve the plan seed: an explicit integer wins; otherwise the
     * deterministic default (U-02) in deterministic mode, or a fresh
     * system seed in random mode (stored on the run, blueprint §9.2).
     */
    private function resolveSeed(array $input): int
    {
        $mode = (string) ($input['mode'] ?? $this->defaultMode());

        if (! in_array($mode, ['random', 'deterministic'], true)) {
            throw ValidationException::withMessages(['mode' => "Mode harus 'random' atau 'deterministic'."]);
        }

        $raw = $input['seed'] ?? null;

        if ($raw === null || $raw === '') {
            return $mode === 'deterministic' ? $this->defaultSeed() : random_int(1, PHP_INT_MAX);
        }

        $seed = $this->toInt($raw);

        if ($seed === null) {
            throw ValidationException::withMessages(['seed' => 'Seed harus bilangan bulat.']);
        }

        return $seed;
    }

    /**
     * Account-creation seed: null in random mode (the generator picks
     * one), the default in deterministic mode when absent.
     */
    private function optionalSeed(array $input, string $mode): ?int
    {
        $raw = $input['seed'] ?? null;

        if ($raw === null || $raw === '') {
            return $mode === 'deterministic' ? $this->defaultSeed() : null;
        }

        $seed = $this->toInt($raw);

        if ($seed === null) {
            throw ValidationException::withMessages(['seed' => 'Seed harus bilangan bulat.']);
        }

        return $seed;
    }

    /**
     * 'Y-m-d H:i' in WITA → UTC instant. Must lie inside the period
     * (the planner enforces it again; this gives the form error early).
     */
    private function parseSinglePoint(mixed $value, PeriodRange $period): CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages(['single_point' => "Distribusi 'satu titik' membutuhkan tanggal+jam."]);
        }

        $timezone = (string) config('ira.reporting_timezone', 'Asia/Makassar');
        $parsed = CarbonImmutable::createFromFormat('Y-m-d H:i', trim($value), $timezone);

        if ($parsed === false || $parsed->format('Y-m-d H:i') !== trim($value)) {
            throw ValidationException::withMessages(['single_point' => "Format tanggal+jam tidak valid: '{$value}' (harus YYYY-MM-DD HH:MM, WITA)."]);
        }

        $utc = $parsed->setTimezone('UTC');

        if (! $period->contains($utc)) {
            throw ValidationException::withMessages(['single_point' => "Titik waktu tunggal harus berada di dalam periode ({$period->startDate} s.d. {$period->endDate})."]);
        }

        return $utc;
    }

    /** [min, max] pair (list or assoc), or null → default. */
    private function validateRange(mixed $value, string $key, array $default, int $minFloor, ?int $maxCeil): array
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (! is_array($value)) {
            throw ValidationException::withMessages([$key => "'{$key}' harus pasangan [min, max]."]);
        }

        $min = $this->toInt($value['min'] ?? $value[0] ?? null);
        $max = $this->toInt($value['max'] ?? $value[1] ?? null);

        $ceiling = $maxCeil !== null ? ", maks ≤ {$maxCeil}" : '';

        if ($min === null || $max === null || $min < $minFloor || $min > $max || ($maxCeil !== null && $max > $maxCeil)) {
            throw ValidationException::withMessages([$key => "'{$key}' tidak valid (min ≥ {$minFloor}, min ≤ max{$ceiling})."]);
        }

        return [$min, $max];
    }

    /** Assoc status => pct summing to 100, or null → config default. */
    private function validateStatusMix(mixed $value): array
    {
        if ($value === null || $value === '') {
            $value = config('simulation.status_mix', ['selesai' => 80, 'dibatalkan' => 10, 'kedaluwarsa' => 10]);
        }

        if (! is_array($value) || $value === []) {
            throw ValidationException::withMessages(['status_mix' => 'Campuran status akhir tidak boleh kosong.']);
        }

        $sum = 0;
        $normalized = [];

        // Fixed key order, so equivalent inputs hash identically.
        foreach (StatusAssigner::FINAL_STATUSES as $status) {
            if (! array_key_exists($status, $value)) {
                continue;
            }

            $pct = $this->toInt($value[$status]);

            if ($pct === null || $pct < 0 || $pct > 100) {
                throw ValidationException::withMessages(['status_mix' => "Persentase status '{$status}' harus bilangan bulat 0–100."]);
            }

            $normalized[$status] = $pct;
            $sum += $pct;
        }

        foreach (array_keys($value) as $status) {
            if (! in_array($status, StatusAssigner::FINAL_STATUSES, true)) {
                throw ValidationException::withMessages(['status_mix' => "Status akhir tidak dikenal: '{$status}'."]);
            }
        }

        if ($sum !== 100) {
            throw ValidationException::withMessages(['status_mix' => "Campuran status akhir harus berjumlah 100% (saat ini {$sum}%)."]);
        }

        return $normalized;
    }

    private function validateRatio(mixed $value, string $key, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $ratio = $this->toInt($value);

        if ($ratio === null || $ratio < 1 || $ratio > 100) {
            throw ValidationException::withMessages([$key => "'{$key}' harus bilangan bulat 1–100 (SIM-12)."]);
        }

        return $ratio;
    }

    private function validateEnum(mixed $value, array $allowed, string $key): string
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw ValidationException::withMessages([$key => "'{$key}' harus salah satu dari: ".implode(', ', $allowed).'.']);
        }

        return $value;
    }

    // ---- Config accessors --------------------------------------------------

    private function maxAccounts(): int
    {
        // SIM-03: the cap can never be configured above 100.
        return min(100, max(1, (int) config('simulation.max_accounts_per_request', 100)));
    }

    private function maxTransactions(): int
    {
        return max(1, (int) config('simulation.max_transactions_per_request', 500));
    }

    private function maxEvents(): int
    {
        return max(1, (int) config('simulation.max_events_per_request', 2000));
    }

    private function defaultMode(): string
    {
        $mode = (string) config('simulation.default_mode', 'deterministic');

        return in_array($mode, ['random', 'deterministic'], true) ? $mode : 'deterministic';
    }

    private function defaultSeed(): int
    {
        return (int) config('simulation.default_seed', 20261101);
    }

    private function simDomain(): string
    {
        return (string) config('simulation.email_domain', 'sim.ira-stationery.test');
    }

    /** @return array<string,array{0:int, 1:int}> */
    private function lagMinutes(): array
    {
        $lags = config('simulation.lag_minutes', FunnelPlanner::DEFAULT_LAG_MINUTES);

        return [
            'view_to_cart' => [(int) ($lags['view_to_cart'][0] ?? 1), (int) ($lags['view_to_cart'][1] ?? 30)],
            'cart_to_booking' => [(int) ($lags['cart_to_booking'][0] ?? 1), (int) ($lags['cart_to_booking'][1] ?? 20)],
            'booking_to_paid' => [(int) ($lags['booking_to_paid'][0] ?? 1), (int) ($lags['booking_to_paid'][1] ?? 10)],
        ];
    }

    /** Active payment-method codes, in config order (D1). */
    private function activePaymentMethods(): array
    {
        $codes = [];

        foreach ((array) config('payment.methods', []) as $code => $method) {
            if (($method['active'] ?? false) === true) {
                $codes[] = (string) ($method['code'] ?? $code);
            }
        }

        return $codes;
    }

    private function paymentExpiryMinutes(): int
    {
        return max(1, (int) config('ira.payment_expiry_minutes', 30));
    }

    /**
     * Integer or integer-like string ('50', '-3') → int; anything else
     * (floats, '5.5', 'abc', bool) → null. Form inputs arrive as strings,
     * so this is the single choke point for integer parsing.
     */
    private function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return null;
    }
}
