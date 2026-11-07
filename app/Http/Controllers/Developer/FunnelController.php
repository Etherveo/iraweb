<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Concerns\ExecutesSimulationPreview;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExecuteFunnelRequest;
use App\Http\Requests\PreviewFunnelRequest;
use App\Simulation\FunnelPlanner;
use App\Simulation\SimulationAudit;
use App\Simulation\SimulationExecutor;
use App\Simulation\SimulationRequestValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// M4 simulator (SIM-31): explicit funnel action only — no presets
// (SIM-32/33 are M7). Preview-mandatory (SIM-25) like the transaction
// actions; the execute POST rebuilds the plan and checks its hash
// (SIM-26, D5).
class FunnelController extends Controller
{
    use ExecutesSimulationPreview;

    public function __construct(
        private SimulationRequestValidator $validator,
        private FunnelPlanner $planner,
        private SimulationAudit $audit,
        private SimulationExecutor $executor,
    ) {}

    public function create(): View
    {
        return view('developer.funnel', array_merge($this->simulatorFormData(), [
            'title' => 'Simulator — Funnel',
            'maxEvents' => (int) config('simulation.max_events_per_request', 2000),
        ]));
    }

    public function preview(PreviewFunnelRequest $request): View
    {
        $params = $this->validator->validateFunnel($request->validated());
        $plan = $this->planner->planFunnel($params, $params['seed']);

        return view('developer.preview', array_merge(
            $this->previewData(
                $plan,
                $params,
                $request->validated(),
                'developer.funnel.execute',
                'developer.funnel.create',
                'Funnel',
            ),
            ['title' => 'Simulator — Pratinjau Funnel'],
        ));
    }

    public function execute(ExecuteFunnelRequest $request): RedirectResponse
    {
        $developer = $this->actingDeveloper($request);

        $params = $this->validator->validateFunnel($request->validated());
        $plan = $this->planner->planFunnel($params, $params['seed']);

        $run = $this->executeVerifiedPlan(
            $this->audit,
            $this->executor,
            $developer,
            'funnel_events',
            $params,
            $plan,
            (string) $request->input('idempotency_key'),
            (string) $request->input('params_hash'),
        );

        return redirect()
            ->route('developer.run.show', $run)
            ->with('status', 'Simulasi funnel selesai: '.($run->counts['orders'] ?? 0).' booking dibuat (run '.$run->id.').');
    }
}
