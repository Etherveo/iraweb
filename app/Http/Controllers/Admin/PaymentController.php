<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\OrderExpiryService;
use App\Services\PaymentTracePanel;
use Illuminate\Http\Request;
use Illuminate\View\View;

// M3c PAY-51/52. Read-only payment list + detail for Admin Toko.
// There is deliberately NO action that changes a payment here
// (PAY-53): no status form, no route, no service call.
class PaymentController extends Controller
{
    public function __construct(
        private readonly OrderExpiryService $expiry,
        private readonly PaymentTracePanel $panel,
    ) {}

    // PAY-51: all payments, newest first, paginated; filters status,
    // method, date range. The origin column shows Normal / SIMULASI.
    public function index(Request $request): View
    {
        $this->expiry->expireOverdue();

        $payments = Payment::query()
            ->with(['order', 'user'])
            ->when(in_array($request->input('status'), Payment::STATUSES, true),
                fn ($q) => $q->where('status', $request->input('status')))
            ->when(in_array($request->input('metode'), array_keys(config('payment.methods', [])), true),
                fn ($q) => $q->where('payment_method', $request->input('metode')))
            ->when($this->isDate($request->input('dari')),
                fn ($q) => $q->whereDate('created_at', '>=', $request->input('dari')))
            ->when($this->isDate($request->input('sampai')),
                fn ($q) => $q->whereDate('created_at', '<=', $request->input('sampai')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pembayaran-index', [
            'payments' => $payments,
            'methods' => config('payment.methods', []),
        ]);
    }

    // PAY-52: detail with the HTTP panel + timeline, read-only.
    public function show(string $payment): View
    {
        $found = Payment::query()->findOrFail($payment);
        $this->expiry->expireIfOverdue($found->order);
        $found = $found->fresh(['order.items', 'transitions', 'user']);

        return view('admin.pembayaran-detail', [
            'payment' => $found,
            'order' => $found->order,
            'method' => config('payment.methods.'.$found->payment_method, []),
            'panel' => $this->panel->forPayment($found),
        ]);
    }

    private function isDate(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
