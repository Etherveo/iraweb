<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\OrderExpiryService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// M3a ORD-07..09. Orders are always resolved scoped to the acting
// user (user_id + id), so another buyer's order id behaves exactly
// like a missing one (ACC-09). Overdue orders expire on access
// (PAY-35) before the page renders.
class OrderController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly OrderExpiryService $expiry,
        private readonly PaymentService $payments,
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::guard('web')->user();
        $this->expiry->expireOverdueForUser($user);

        $orders = Order::query()
            ->where('user_id', $user->id)
            ->when(in_array($request->input('status'), Order::STATUSES, true),
                fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('shop.pesanan-index', ['orders' => $orders]);
    }

    public function show(int $order): View
    {
        $user = Auth::guard('web')->user();
        $found = Order::query()
            ->where('user_id', $user->id)
            ->findOrFail($order);

        $this->expiry->expireIfOverdue($found);

        $found->load(['items.product', 'histories', 'payments.transitions', 'user']);

        // M3b PAY-31: the detail page offers repay (with a method
        // choice) when the last payment failed and the deadline is
        // still open.
        return view('shop.pesanan-detail', [
            'order' => $found->fresh(['items.product', 'histories', 'payments.transitions']),
            'methods' => $this->payments->methods(),
        ]);
    }

    public function cancel(int $order): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $found = Order::query()
            ->where('user_id', $user->id)
            ->findOrFail($order);

        $cancelled = $this->checkout->cancelOrder($user, $found);

        return redirect()->route('pesanan.show', $cancelled)
            ->with('status', 'Pesanan '.$cancelled->order_number.' dibatalkan. Stok yang dicadangkan sudah dilepas.');
    }
}
