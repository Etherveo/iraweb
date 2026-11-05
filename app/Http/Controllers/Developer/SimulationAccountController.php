<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

// ACC-14: read-only list of simulation accounts, never normal accounts,
// never passwords. Filtering is by simulation run id only (M4 runs do not
// exist yet, so the filter simply returns an empty page for unknown runs).
class SimulationAccountController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = User::query()
            ->where('role', 'pembeli')
            ->where('origin', 'developer_simulation')
            ->when($request->filled('run'), function ($query) use ($request) {
                $query->where('simulation_run_id', $request->input('run'));
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('developer.akun-simulasi-index', ['accounts' => $accounts]);
    }
}
