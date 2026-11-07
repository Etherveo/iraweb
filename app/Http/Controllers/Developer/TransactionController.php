<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Concerns\ExecutesSimulationPreview;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExecutePeriodFirstRequest;
use App\Http\Requests\ExecuteUserFirstRequest;
use App\Http\Requests\PreviewPeriodFirstRequest;
use App\Http\Requests\PreviewUserFirstRequest;
use App\Simulation\FunnelPlanner;
use App\Simulation\SimulationAudit;
use App\Simulation\SimulationExecutor;
use App\Simulation\SimulationRequestValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// M4 simulator (SIM-09..24): transaction actions 2 (user-first) and 3
// (period-first). Both are preview-mandatory (SIM-25): the preview
// POST builds the plan and shows it; the execute POST rebuilds the
// same plan deterministically and checks its hash (SIM-26, D5).
class TransactionController extends Controller
{
    use ExecutesSimulationPreview;

    public function __construct(
        private SimulationRequestValidator $validator,
        private FunnelPlanner $planner,
        private SimulationAudit $audit,
        private SimulationExecutor $executor,
    ) {}

    // ---- Aksi 2: user-first (SIM-09..15) ----

    public function userForm(): View
    {
        return view('developer.tx-user', array_merge($this->simulatorFormData(), [
            'title' => 'Simulator — Transaksi per User',
        ]));
    }

    public function userPreview(PreviewUserFirstRequest $request): View
    {
        $params = $this->validator->validateUserFirst($request->validated());
        $plan = $this->planner->plan($params, $params['seed']);

        return view('developer.preview', array_merge(
            $this->previewData(
                $plan,
                $params,
                $request->validated(),
                'developer.tx-user.execute',
                'developer.tx-user.create',
                'Transaksi per User',
            ),
            ['title' => 'Simulator — Pratinjau Transaksi per User'],
        ));
    }

    public function userExecute(ExecuteUserFirstRequest $request): RedirectResponse
    {
        $developer = $this->actingDeveloper($request);

        $params = $this->validator->validateUserFirst($request->validated());
        $plan = $this->planner->plan($params, $params['seed']);

        $run = $this->executeVerifiedPlan(
            $this->audit,
            $this->executor,
            $developer,
            'tx_user_first',
            $params,
            $plan,
            (string) $request->input('idempotency_key'),
            (string) $request->input('params_hash'),
        );

        return redirect()
            ->route('developer.run.show', $run)
            ->with('status', 'Simulasi transaksi per user selesai: '.($run->counts['orders'] ?? 0).' pesanan dibuat (run '.$run->id.').');
    }

    // ---- Aksi 3: period-first (SIM-16..24) ----

    public function periodForm(): View
    {
        return view('developer.tx-period', array_merge($this->simulatorFormData(), [
            'title' => 'Simulator — Transaksi per Periode',
            'defaultScope' => 'all',
        ]));
    }

    public function periodPreview(PreviewPeriodFirstRequest $request): View
    {
        $params = $this->validator->validatePeriodFirst($request->validated());
        $plan = $this->planner->plan($params, $params['seed']);

        return view('developer.preview', array_merge(
            $this->previewData(
                $plan,
                $params,
                $request->validated(),
                'developer.tx-period.execute',
                'developer.tx-period.create',
                'Transaksi per Periode',
            ),
            ['title' => 'Simulator — Pratinjau Transaksi per Periode'],
        ));
    }

    public function periodExecute(ExecutePeriodFirstRequest $request): RedirectResponse
    {
        $developer = $this->actingDeveloper($request);

        $params = $this->validator->validatePeriodFirst($request->validated());
        $plan = $this->planner->plan($params, $params['seed']);

        $run = $this->executeVerifiedPlan(
            $this->audit,
            $this->executor,
            $developer,
            'tx_period_first',
            $params,
            $plan,
            (string) $request->input('idempotency_key'),
            (string) $request->input('params_hash'),
        );

        return redirect()
            ->route('developer.run.show', $run)
            ->with('status', 'Simulasi transaksi per periode selesai: '.($run->counts['orders'] ?? 0).' pesanan dibuat (run '.$run->id.').');
    }
}
