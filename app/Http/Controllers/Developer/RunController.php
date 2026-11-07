<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SimulationRun;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

// M4 simulator (SIM-35/37): run trace — list with type/status/date
// filters, and detail with params, row counts, audit log, and links
// to the generated accounts/orders.
class RunController extends Controller
{
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
}
