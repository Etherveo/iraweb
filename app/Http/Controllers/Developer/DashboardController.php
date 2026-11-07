<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\SimulationRun;
use App\Models\User;
use Illuminate\View\View;

// M4 simulator (SIM-01/35, D7): developer panel home — simulation
// account/order/event counts, run counts + latest run, quick links to
// the three simulation actions.
class DashboardController extends Controller
{
    public function index(): View
    {
        return view('developer.dashboard-index', [
            'title' => 'Simulator — Beranda',
            'simAccountCount' => User::query()->where('origin', 'developer_simulation')->count(),
            'simOrderCount' => Order::query()->where('origin', 'developer_simulation')->count(),
            'simEventCount' => Event::query()->where('origin', 'developer_simulation')->count(),
            'runCount' => SimulationRun::query()->count(),
            'runsByStatus' => SimulationRun::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all(),
            'latestRun' => SimulationRun::query()->orderByDesc('started_at')->first(),
            'recentRuns' => SimulationRun::query()->orderByDesc('started_at')->limit(5)->get(),
        ]);
    }
}
