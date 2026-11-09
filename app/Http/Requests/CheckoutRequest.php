<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// ORD-01/ORD-02: a payment method from the active config catalog
// (PAY-02) and the idempotency key issued with the checkout form.
// There is deliberately NO amount/total field: the total is computed
// on the server (ORD-04) and any client-supplied amount is ignored.
//
// Jadwal pengambilan (keputusan Ren 2026-11-09): the buyer must pick a
// pickup date + time BEFORE paying, so the order knows when it will be
// collected at the store. Input is WITA (the reporting timezone);
// pickupAt() combines it and returns UTC for storage.
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $active = array_keys(array_filter(
            config('payment.methods', []),
            fn (array $method) => ($method['active'] ?? false) === true,
        ));

        return [
            'payment_method' => ['required', 'string', Rule::in($active)],
            'checkout_key' => ['nullable', 'string', 'max:64'],
            'pickup_date' => ['required', 'date', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && $value < Carbon::now($this->reportingTimezone())->toDateString()) {
                    $fail('Tanggal pengambilan tidak boleh lewat.');
                }
            }],
            'pickup_time' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $date = $this->input('pickup_date');
            $time = $this->input('pickup_time');

            if (! is_string($date) || ! is_string($time)) {
                return;
            }

            try {
                $at = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time, $this->reportingTimezone());
            } catch (\Exception) {
                return;
            }

            if ($at->lessThanOrEqualTo(Carbon::now($this->reportingTimezone()))) {
                $validator->errors()->add('pickup_time', 'Jadwal pengambilan harus di masa depan.');
            }
        });
    }

    // Combined pickup datetime in UTC (input is WITA).
    public function pickupAt(): Carbon
    {
        return Carbon::createFromFormat(
            'Y-m-d H:i',
            $this->validated('pickup_date').' '.$this->validated('pickup_time'),
            $this->reportingTimezone(),
        )->setTimezone('UTC');
    }

    private function reportingTimezone(): string
    {
        return (string) config('ira.reporting_timezone', 'Asia/Makassar');
    }
}
