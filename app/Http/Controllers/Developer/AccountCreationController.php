<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Concerns\ExecutesSimulationPreview;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSimulationAccountsRequest;
use App\Models\User;
use App\Simulation\BiodataGenerator;
use App\Simulation\SimulationAccountService;
use App\Simulation\SimulationRequestValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

// M4 simulator (SIM-02..08): bulk simulation-account creation. The
// controller stays thin: validate the input, hand it to
// SimulationAccountService (which opens the run itself — the effective
// seed is only known after generation), show the run. No preview here:
// SIM-25 mandates previews only for actions 2, 3 and 4.
class AccountCreationController extends Controller
{
    use ExecutesSimulationPreview;

    public function __construct(
        private SimulationRequestValidator $validator,
        private SimulationAccountService $accounts,
    ) {}

    public function create(): View
    {
        return view('developer.akun-create', [
            'title' => 'Simulator — Buat Akun',
            'simAccountCount' => User::query()->where('origin', 'developer_simulation')->count(),
            'passwordConfigured' => ! blank(config('simulation.account_password')),
            'defaultMode' => (string) config('simulation.default_mode', 'deterministic'),
            'defaultSeed' => (int) config('simulation.default_seed', 20261101),
            'maxAccounts' => (int) config('simulation.max_accounts_per_request', 100),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(StoreSimulationAccountsRequest $request): RedirectResponse
    {
        $developer = $this->actingDeveloper($request);

        $input = $this->validator->validateAccountCreation($request->validated());

        $result = $this->accounts->createAccounts(
            $developer,
            app(BiodataGenerator::class),
            $input,
            (string) $request->input('idempotency_key'),
        );

        return redirect()
            ->route('developer.run.show', $result['run'])
            ->with('status', count($result['users']).' akun simulasi dibuat (run '.$result['run']->id.').');
    }
}
