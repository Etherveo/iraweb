<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Concerns\ExecutesSimulationPreview;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SimulationRun;
use App\Models\User;
use App\Simulation\BiodataGenerator;
use App\Simulation\FunnelPlanner;
use App\Simulation\SimulationAccountService;
use App\Simulation\SimulationAudit;
use App\Simulation\SimulationExecutor;
use App\Simulation\SimulationPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

// M4 simulator (SIM-35/37): run trace — list with type/status/date
// filters, and detail with params, row counts, audit log, and links
// to the generated accounts/orders.
// Retry/cancel (keputusan Ren 2026-11-11): a run whose execution died
// mid-request stays 'running' forever — the request is gone, so the
// row is stale, not busy. "Coba lagi" replays it as a NEW run with
// identical params; "Batalkan" only marks it 'gagal' (with an audit
// row) so Jejak & Audit stops showing a stale 'Berjalan'.
class RunController extends Controller
{
    use ExecutesSimulationPreview;

    public const ACTION_LABELS = [
        'create_accounts' => 'Buat Akun',
        'tx_user_first' => 'Transaksi per User',
        'tx_period_first' => 'Transaksi per Periode',
        'funnel_events' => 'Funnel',
    ];

    public const STATUS_LABELS = [
        'running' => 'Berjalan',
        'berhasil' => 'Berhasil',
        'gagal' => 'Gagal',
        'direset' => 'Direset',
    ];

    public function __construct(
        private FunnelPlanner $planner,
        private SimulationAudit $audit,
        private SimulationExecutor $executor,
        private SimulationAccountService $accounts,
    ) {}

    public function index(Request $request): View
    {
        $action = in_array($request->input('action'), SimulationRun::ACTIONS, true)
            ? $request->input('action')
            : null;

        $status = in_array($request->input('status'), SimulationRun::STATUSES, true)
            ? $request->input('status')
            : null;

        $date = is_string($request->input('date'))
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->input('date')) === 1
            ? $request->input('date')
            : null;

        $runs = SimulationRun::query()
            ->when($action !== null, fn ($query) => $query->where('action', $action))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($date !== null, fn ($query) => $query->whereDate('started_at', $date))
            ->orderByDesc('started_at')
            ->paginate(20)
            ->withQueryString();

        return view('developer.run-index', [
            'title' => 'Simulator — Jejak & Audit',
            'runs' => $runs,
            'actionLabels' => self::ACTION_LABELS,
            'statusLabels' => self::STATUS_LABELS,
            'filters' => ['action' => $action, 'status' => $status, 'date' => $date],
        ]);
    }

    public function show(SimulationRun $run): View
    {
        $run->load(['auditLogs' => fn ($query) => $query->orderByDesc('occurred_at')]);

        return view('developer.run-show', [
            'title' => 'Simulator — Detail Run',
            'run' => $run,
            'actionLabels' => self::ACTION_LABELS,
            'statusLabels' => self::STATUS_LABELS,
            'accounts' => User::query()->where('simulation_run_id', $run->id)->orderBy('id')->limit(100)->get(),
            'orders' => Order::query()->where('simulation_run_id', $run->id)->with('user')->orderBy('id')->paginate(20),
            'eventCount' => Event::query()->where('simulation_run_id', $run->id)->count(),
            'paymentCount' => Payment::query()->where('simulation_run_id', $run->id)->count(),
        ]);
    }

    /**
     * Retry a stuck ('running') or failed ('gagal') run: a NEW run
     * with identical params (same action, seed, period, scope — read
     * from the original run's stored params), a fresh idempotency
     * key, executed through the same path as the original action.
     * The stored params were already previewed once, so there is no
     * preview step here; the old run is left untouched.
     */
    public function retry(Request $request, SimulationRun $run): RedirectResponse
    {
        $developer = $this->actingDeveloper($request);

        if (! in_array($run->status, ['running', 'gagal'], true)) {
            throw ValidationException::withMessages([
                'run' => 'Run berstatus '.$run->status.' tidak dapat diulangi — hanya run "Berjalan" atau "Gagal" yang bisa dicoba lagi.',
            ]);
        }

        $stored = $run->params;
        $paramsHash = $stored['params_hash'] ?? null;
        unset($stored['params_hash']);

        if ($run->action === 'create_accounts') {
            $result = $this->accounts->createAccounts(
                $developer,
                app(BiodataGenerator::class),
                $stored,
                (string) Str::uuid(),
            );

            $newRun = $result['run'];
            $message = count($result['users']).' akun simulasi dibuat (run '.$newRun->id.').';
        } else {
            $plan = $this->rebuildPlan($run, $stored);

            // Bila run lama tidak menyimpan params_hash (mis. dibuat
            // sebelum hash disimpan), pakai hash plan yang baru
            // dibangun — params-nya tetap berasal dari DB, bukan dari
            // request, jadi tidak ada yang bisa diubah diam-diam.
            $newRun = $this->executeVerifiedPlan(
                $this->audit,
                $this->executor,
                $developer,
                $run->action,
                $stored,
                $plan,
                (string) Str::uuid(),
                $paramsHash ?? $plan->paramsHash,
            );

            $message = 'Simulasi diulang: '.($newRun->counts['orders'] ?? 0).' pesanan dibuat (run '.$newRun->id.').';
        }

        return redirect()
            ->route('developer.run.show', $newRun)
            ->with('status', $message);
    }

    /**
     * Cancel a stuck ('running') run. Nothing is actually running —
     * the request already died — so this only marks the run 'gagal'
     * with a manual-cancellation note and writes the audit row, so
     * Jejak & Audit stops showing a stale 'Berjalan'. A cancelled
     * run created no data, so like a failed run it has nothing to
     * reset (reset hanya menyentuh run 'berhasil').
     */
    public function cancel(Request $request, SimulationRun $run): RedirectResponse
    {
        // Guard eksplisit (rute sudah auth:developer): tanpa Developer
        // yang sah, tidak ada aksi yang boleh jalan.
        $this->actingDeveloper($request);

        if ($run->status !== 'running') {
            throw ValidationException::withMessages([
                'run' => 'Run berstatus '.$run->status.' tidak dapat dibatalkan — hanya run "Berjalan" yang bisa dibatalkan.',
            ]);
        }

        $this->audit->recordRunFailed($run, 'Dibatalkan manual oleh developer — run nyangkut.');

        return redirect()
            ->route('developer.run.index')
            ->with('status', 'Run '.$run->id.' dibatalkan.');
    }

    /**
     * Rebuild the execution plan from a run's stored params (SIM-26):
     * the planner is a pure function of (params, seed), so the same
     * params + seed rebuild the same plan. The stored params_hash is
     * stripped by the caller first — executeVerifiedPlan merges it
     * back in when the new run is opened.
     */
    private function rebuildPlan(SimulationRun $run, array $params): SimulationPlan
    {
        return match ($run->action) {
            'tx_user_first', 'tx_period_first' => $this->planner->plan($params, $run->seed),
            'funnel_events' => $this->planner->planFunnel($params, $run->seed),
            default => throw new InvalidArgumentException("Aksi simulasi tidak dikenal: '{$run->action}'."),
        };
    }
}
