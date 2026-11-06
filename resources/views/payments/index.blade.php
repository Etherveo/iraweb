@extends('layouts.app')

@section('content')
    {{-- UI polish Tahap 7 (UI.md §2.4, §6, §9.3, §10, §16, referensi Figma 3:105077).
         Filter status, tabel riwayat pembayaran milik pembeli, dan pagination dipertahankan. --}}
    @php
        $paymentChips = [
            'pending' => 'status-chip-warning',
            'paid' => 'status-chip-success',
            'failed' => 'status-chip-danger',
            'expired' => 'status-chip-muted',
            'cancelled' => 'status-chip-muted',
        ];
    @endphp
    <div class="admin-page">
        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Toko Ira &bull; Pembayaran</p>
                <h1 class="admin-title">Riwayat Pembayaran — SIMULASI</h1>
                <p class="admin-subtitle">Semua pembayaran simulasi milikmu. Status hanya dapat dilihat, bukan diedit.</p>
            </div>
            <div class="admin-head-actions">
                <a class="btn-secondary" href="{{ route('pesanan.index') }}">Kembali ke daftar pesanan</a>
            </div>
        </header>

        <div class="admin-sim-banner">
            <strong>⚠ SIMULASI — bukan pembayaran riil.</strong> Tidak ada uang yang berpindah; semua baris di bawah ini adalah pembayaran simulasi.
        </div>

        <div class="card admin-panel">
            <form method="GET" action="{{ route('pembayaran.index') }}" class="admin-filters">
                <div class="admin-filter-group">
                    <label class="admin-label" for="status">Status</label>
                    <select class="input" name="status" id="status">
                        <option value="">Semua status</option>
                        @foreach (\App\Models\Payment::STATUS_LABELS as $code => $label)
                            <option value="{{ $code }}" @selected(request('status') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter-actions">
                    <button class="btn-primary" type="submit">Filter</button>
                    @if (request()->filled('status'))
                        <a class="btn-secondary" href="{{ route('pembayaran.index') }}">Reset</a>
                    @endif
                </div>
            </form>

            @if ($payments->isEmpty())
                <div class="admin-empty">
                    <p class="admin-empty-title">Belum ada pembayaran.</p>
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Pesanan</th>
                                <th>Metode</th>
                                <th>Nominal</th>
                                <th>Status</th>
                                <th>Waktu</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td data-label="Pesanan">
                                    <span class="admin-cell-primary">{{ $payment->order->order_number }}</span>
                                    <div class="admin-cell-sub"><code class="admin-code">{{ $payment->reference_code }}</code></div>
                                </td>
                                <td data-label="Metode">
                                    <strong>{{ config('payment.methods.'.$payment->payment_method.'.label', $payment->payment_method) }}</strong>
                                    <div class="admin-cell-sub"><span class="badge-simulasi">SIMULASI</span></div>
                                </td>
                                <td data-label="Nominal" class="admin-cell-price">{{ $payment->formattedAmount() }}</td>
                                <td data-label="Status">
                                    <span class="status-chip {{ $paymentChips[$payment->status] ?? 'status-chip-muted' }}">
                                        <span>{{ $payment->statusLabel() }}</span>
                                    </span>
                                </td>
                                <td data-label="Waktu" class="admin-cell-nowrap">
                                    {{ $payment->created_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA
                                </td>
                                <td data-label="Aksi">
                                    <a class="admin-action-link" href="{{ route('pembayaran.show', $payment) }}">Detail &amp; jejak</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="admin-pagination">
                    {{ $payments->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
