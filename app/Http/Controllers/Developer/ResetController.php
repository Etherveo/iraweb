<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Concerns\ExecutesSimulationPreview;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResetAllRequest;
use App\Http\Requests\ResetRunRequest;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SimulationRun;
use App\Models\User;
use App\Simulation\SimulationResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// M4 simulator (SIM-38/39/40): reset per run and reset-all, both
// behind a type-the-phrase confirmation. The service scopes every
// delete to origin=developer_simulation and never touches the audit
// table; the panel additionally refuses any reset while a run is
// 'running' (the service assumes no run is in flight — a reset
// mid-run would delete the in-flight run's partial data while the
// run itself stays 'running').
class ResetController extends Controller
{
    use ExecutesSimulationPreview;

    public function __construct(
        private SimulationResetService $reset,
    ) {}

    public function index(): View
    {
        return view('developer.reset-index', [
            'title' => 'Simulator — Reset',
            'runningExists' => SimulationRun::query()->where('status', 'running')->exists(),
            'resettableRuns' => SimulationRun::query()->where('status', 'berhasil')->orderByDesc('started_at')->paginate(20),
            'simAccountCount' => User::query()->where('origin', 'developer_simulation')->count(),
            'simOrderCount' => Order::query()->where('origin', 'developer_simulation')->count(),
            'simEventCount' => Event::query()->where('origin', 'developer_simulation')->count(),
            'simPaymentCount' => Payment::query()->where('origin', 'developer_simulation')->count(),
        ]);
    }

    public function resetRun(ResetRunRequest $request, SimulationRun $run): RedirectResponse
    {
        $developer = $this->actingDeveloper($request);

        $this->guardNoRunningRun();

        $summary = $this->reset->resetRun($run, $developer);

        return redirect()
            ->route('developer.reset.index')
            ->with('status', 'Run '.$run->id.' direset.')
            ->with('reset_summary', $summary);
    }

    public function resetAll(ResetAllRequest $request): RedirectResponse
    {
        $developer = $this->actingDeveloper($request);

        $this->guardNoRunningRun();

        $summary = $this->reset->resetAll($developer);

        return redirect()
            ->route('developer.reset.index')
            ->with('status', 'Seluruh data simulasi direset.')
            ->with('reset_summary', $summary);
    }

    private function guardNoRunningRun(): void
    {
        if (SimulationRun::query()->where('status', 'running')->exists()) {
            throw ValidationException::withMessages([
                'reset' => 'Ada run yang masih berjalan — reset ditunda sampai run selesai (SIM-39).',
            ]);
        }
    }
}
