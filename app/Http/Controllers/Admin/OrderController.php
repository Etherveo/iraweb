<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderExpiryService;
use App\Services\OrderStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// M3a ORD-10..13. Read-only except ORD-12 (fulfillment). There is no
// admin cancel action (ORD-13): cancelled/expired orders are only
// listed, with their reason, time and trigger. Overdue orders expire
// on access (PAY-35) before the page renders.
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly OrderExpiryService $expiry,
    ) {}

    public function index(Request $request): View
    {
        $this->expiry->expireOverdue();

        $orders = Order::query()
            ->with('user')
            ->when(in_array($request->input('status'), Order::STATUSES, true),
                fn ($q) => $q->where('status', $request->input('status')))
            ->when(in_array($request->input('asal'), ['normal', 'developer_simulation'], true),
                fn ($q) => $q->where('origin', $request->input('asal')))
            ->when($this->isDate($request->input('dari')),
                fn ($q) => $q->whereDate('created_at', '>=', $request->input('dari')))
            ->when($this->isDate($request->input('sampai')),
                fn ($q) => $q->whereDate('created_at', '<=', $request->input('sampai')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $request->input('q')).'%';
                $q->whereRaw("order_number LIKE ? ESCAPE '\\'", [$term]);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pesanan-index', ['orders' => $orders]);
    }

    public function show(int $order): View
    {
        $found = Order::query()->findOrFail($order);
        $this->expiry->expireIfOverdue($found);

        $found->load(['items.product', 'histories', 'payments.transitions', 'user']);

        return view('admin.pesanan-detail', ['order' => $found->fresh(['items.product', 'histories', 'payments.transitions'])]);
    }

    // ORD-12: only dibayar -> diproses and diproses -> selesai. The
    // state machine rejects anything else server-side (e.g. marking a
    // menunggu_pembayaran order as paid, or cancelling a paid order).
    public function updateStatus(AdminOrderStatusRequest $request, int $order): RedirectResponse
    {
        $found = Order::query()->findOrFail($order);
        $admin = Auth::guard('web')->user();

        $updated = $this->stateMachine->transition(
            $found,
            (string) $request->validated('to_status'),
            'admin',
            'admin_toko',
            $admin->id,
        );

        return redirect()->route('admin.pesanan.show', $updated)
            ->with('status', 'Pesanan '.$updated->order_number.' diubah ke '.$updated->statusLabel().'.');
    }

    private function isDate(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
