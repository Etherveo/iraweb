<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// M3a ORD-01/ORD-02. The summary page shows server-computed items and
// total plus the payment-method choice (PAY-02); the POST creates the
// order atomically in CheckoutService. After success the buyer lands
// on the ORDER DETAIL page — the payment instruction page is M3b
// (PAY-11), so there is no instruction page to redirect to yet.
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly PaymentService $payments,
    ) {}

    public function show(): View|RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $view = $this->checkout->cartView($user);

        if ($view['items']->isEmpty()) {
            return redirect()->route('keranjang.index')
                ->withErrors(['keranjang' => 'Keranjang masih kosong.']);
        }

        if ($view['blocked']->isNotEmpty()) {
            return redirect()->route('keranjang.index')
                ->withErrors(['keranjang' => 'Ada item di keranjang yang bermasalah (produk nonaktif atau stok kurang). Perbaiki atau hapus item tersebut sebelum checkout.']);
        }

        return view('shop.checkout-show', [
            'items' => $view['items'],
            'total_idr' => $view['total_idr'],
            'methods' => $this->payments->methods(),
            'checkout_key' => $this->checkout->newCheckoutKey(),
            'expiry_minutes' => $this->checkout->expiryMinutes(),
        ]);
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $order = $this->checkout->checkout(
            Auth::guard('web')->user(),
            (string) $request->validated('payment_method'),
            $request->validated('checkout_key'),
        );

        return redirect()->route('pesanan.show', $order)
            ->with('status', 'Pesanan '.$order->order_number.' dibuat. Batas waktu bayar: '.$order->payment_deadline_at->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i').' WITA.');
    }
}
