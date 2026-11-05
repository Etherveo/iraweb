<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\RepayRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\QrRenderer;
use App\Services\OrderExpiryService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// M3b PAY-06..14, PAY-31. The buyer-facing payment instruction page
// (GET /pesanan/{order}/bayar) and the status endpoint the page polls
// (GET /pembayaran/{payment}/status), plus repay-after-failure
// (POST /pesanan/{order}/bayar-ulang). All scoped to the owning
// buyer (ACC-09); overdue orders expire on access first (PAY-35).
// The result pages (PAY-45..47) are M3c, so a final payment redirects
// the buyer to the order detail page for now.
class PaymentInstructionController extends Controller
{
    public function __construct(
        private readonly OrderExpiryService $expiry,
        private readonly PaymentService $payments,
        private readonly QrRenderer $qr,
    ) {}

    // PAY-11: instruction page for the order's pending payment.
    public function show(int $order): View|RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $found = Order::query()->where('user_id', $user->id)->findOrFail($order);

        $this->expiry->expireIfOverdue($found);
        $found = $found->fresh(['items.product', 'payments']);

        if ($found->status !== 'menunggu_pembayaran') {
            return redirect()->route('pesanan.show', $found)
                ->with('status', 'Pesanan sudah berstatus '.$found->statusLabel().'.');
        }

        $payment = $found->pendingPayment();

        if ($payment === null) {
            return redirect()->route('pesanan.show', $found)
                ->with('status', 'Tidak ada pembayaran yang menunggu untuk pesanan ini.');
        }

        $instruction = $this->payments->instructionFor($payment);
        $method = $this->payments->method($payment->payment_method);

        return view('payments.instruction-show', [
            'order' => $found,
            'payment' => $payment,
            'instruction' => $instruction,
            'method' => $method,
            'qrHtml' => $instruction->qr !== null ? $this->qr->render($instruction->qr) : null,
            'expiresAtIso' => $payment->expires_at->toIso8601String(),
            'serverNowIso' => now()->toIso8601String(),
            'isLoopback' => $this->isLoopbackAppUrl(),
        ]);
    }

    // PAY-13: status polling (owner only). The server decides the
    // status: expiry is processed on access before answering, so the
    // client never determines it. A final payment returns the
    // redirect the page must follow (order detail; the M3c result
    // pages will replace this target).
    public function status(string $payment): JsonResponse
    {
        $user = Auth::guard('web')->user();
        $found = Payment::query()->where('user_id', $user->id)->findOrFail($payment);

        $this->expiry->expireIfOverdue($found->order);
        $found = $found->fresh();
        $order = $found->order()->first();

        return response()->json([
            'status' => $found->status,
            'order_status' => $order->status,
            'redirect_url' => $found->isFinal() ? route('pesanan.show', $order) : null,
        ]);
    }

    // PAY-31: repay after a failed payment. Only while the order is
    // still menunggu_pembayaran, inside its deadline, and with no
    // pending payment left. The new payment gets a fresh token and
    // reference, but expires_at stays the order's payment_deadline_at
    // (the deadline is never extended). The old payment stays failed.
    public function repay(RepayRequest $request, int $order): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $found = Order::query()->where('user_id', $user->id)->findOrFail($order);

        $this->expiry->expireIfOverdue($found);
        $found = $found->fresh(['payments']);

        if ($found->status !== 'menunggu_pembayaran') {
            return redirect()->route('pesanan.show', $found)
                ->withErrors(['status' => 'Pesanan berstatus '.$found->statusLabel().' tidak dapat dibayar ulang.']);
        }

        if (now()->greaterThanOrEqualTo($found->payment_deadline_at)) {
            return redirect()->route('pesanan.show', $found)
                ->withErrors(['status' => 'Batas waktu bayar pesanan sudah terlewati.']);
        }

        if ($found->pendingPayment() !== null) {
            return redirect()->route('pesanan.bayar', $found)
                ->withErrors(['status' => 'Masih ada pembayaran yang menunggu untuk pesanan ini.']);
        }

        try {
            $this->payments->createForOrder($found, (string) $request->validated('payment_method'));
        } catch (ValidationException $e) {
            return redirect()->route('pesanan.show', $found)->withErrors($e->errors());
        }

        return redirect()->route('pesanan.bayar', $found)
            ->with('status', 'Pembayaran baru dibuat. Batas waktu bayar tetap '.$found->payment_deadline_at->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i').' WITA.');
    }

    // PAY-14: the QR cannot be opened from another device when the
    // app URL is a loopback address.
    private function isLoopbackAppUrl(): bool
    {
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || str_starts_with($host, '127.');
    }
}
