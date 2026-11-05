<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// ACC-11/12/13. All queries are scoped to role=pembeli (ACC-09 groundwork:
// buyer_id is never taken from a form/query; the resource id in the URL is
// only ever resolved inside the pembeli scope, so an admin/developer id
// here behaves exactly like a missing id: 404).
class BuyerAccountController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = User::query()
            ->where('role', 'pembeli')
            ->when(in_array($request->input('status'), ['aktif', 'nonaktif'], true), function ($query) use ($request) {
                $query->where('is_active', $request->input('status') === 'aktif');
            })
            ->when(in_array($request->input('asal'), ['normal', 'developer_simulation'], true), function ($query) use ($request) {
                $query->where('origin', $request->input('asal'));
            })
            ->when($request->filled('cari'), function ($query) use ($request) {
                $term = '%'.mb_strtolower((string) $request->input('cari')).'%';
                $query->where(function ($inner) use ($term) {
                    $inner->whereRaw('lower(name) LIKE ?', [$term])
                        ->orWhereRaw('lower(email) LIKE ?', [$term]);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.akun-pembeli-index', ['accounts' => $accounts]);
    }

    public function show(User $account): View
    {
        // Route-model binding must not leak non-pembeli accounts: resolve
        // inside the pembeli scope, otherwise 404 (same as missing).
        $account = User::query()->where('role', 'pembeli')->findOrFail($account->getKey());

        // Order count is a placeholder until M3 creates orders; keep the
        // variable so the M1 view contract does not change in M3.
        $orderCount = 0;

        return view('admin.akun-pembeli-detail', [
            'account' => $account,
            'orderCount' => $orderCount,
        ]);
    }

    public function updateStatus(Request $request, User $account): RedirectResponse
    {
        $account = User::query()->where('role', 'pembeli')->findOrFail($account->getKey());

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $isActive = (bool) $validated['is_active'];

        DB::transaction(function () use ($account, $isActive, $validated) {
            $account->update([
                'is_active' => $isActive,
                'deactivated_at' => $isActive ? null : now(),
                'deactivation_reason' => $isActive ? null : ($validated['reason'] ?? null),
            ]);

            // ACC-13/ACC-07: cut the active database session(s) as well.
            // The EnsureActiveAccount middleware is the second line of
            // defence: any surviving session is invalid on next request.
            if (! $isActive) {
                DB::table('sessions')->where('user_id', $account->getKey())->delete();
            }
        });

        return redirect()->route('admin.akun-pembeli.show', $account)
            ->with('status', $isActive ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    }
}
