@extends('layouts.app')

@section('content')
    <div class="developer-page">
        <div class="developer-head">
            <div>
                <p class="developer-eyebrow">Simulator</p>
                <h1 class="developer-title">Pratinjau — {{ $actionLabel }}</h1>
                <p class="developer-subtitle">Periksa rencana di bawah ini sebelum menjalankan. <strong>Pratinjau ini belum menulis data apa pun</strong> (SIM-25) — data baru dibuat hanya setelah tombol "Jalankan simulasi ini" ditekan.</p>
            </div>
        </div>

        <div class="card">
            <h2 class="developer-section-title">Ringkasan rencana</h2>
            <dl class="developer-kv">
                <div class="developer-kv-row">
                    <dt>Aksi</dt>
                    <dd>{{ $actionLabel }}</dd>
                </div>
                <div class="developer-kv-row">
                    <dt>Periode</dt>
                    <dd>{{ $plan->period->startDate }} s.d. {{ $plan->period->endDate }} ({{ $plan->period->days }} hari, WITA)</dd>
                </div>
                <div class="developer-kv-row">
                    <dt>Seed</dt>
                    <dd>{{ $plan->seed }} (mode {{ $params['mode'] ?? 'deterministic' }})</dd>
                </div>
                <div class="developer-kv-row">
                    <dt>Akun</dt>
                    <dd>
                        {{ count($params['user_ids']) }} akun simulasi
                        @foreach ($accounts->take(5) as $account)
                            <br><small>{{ $account->name }} ({{ $account->email }})</small>
                        @endforeach
                        @if ($accounts->count() > 5)
                            <br><small>… dan {{ $accounts->count() - 5 }} akun lagi.</small>
                        @endif
                        @if ($skippedInactiveIds !== [])
                            <br><small>{{ count($skippedInactiveIds) }} akun nonaktif dilewati (SIM-19).</small>
                        @endif
                    </dd>
                </div>
                <div class="developer-kv-row">
                    <dt>Produk</dt>
                    <dd>
                        {{ count($params['products']) }} produk
                        @foreach ($products->take(5) as $product)
                            <br><small>{{ $product->name }} — Rp{{ number_format($product->price_idr, 0, ',', '.') }}</small>
                        @endforeach
                        @if ($products->count() > 5)
                            <br><small>… dan {{ $products->count() - 5 }} produk lagi.</small>
                        @endif
                    </dd>
                </div>
                @if ($plan->action === 'funnel_events')
                    <div class="developer-kv-row">
                        <dt>Funnel diminta</dt>
                        <dd>view {{ $params['view_count'] }} · add to cart {{ $params['add_to_cart_count'] }} · booking {{ $params['booking_count'] }} · purchase {{ $params['purchase_count'] }}</dd>
                    </div>
                @endif
                <div class="developer-kv-row">
                    <dt>Transaksi</dt>
                    <dd>{{ count($plan->orders) }} pesanan ({{ $statusBreakdown['selesai'] ?? 0 }} selesai · {{ $statusBreakdown['dibatalkan'] ?? 0 }} dibatalkan · {{ $statusBreakdown['kedaluwarsa'] ?? 0 }} kedaluwarsa)</dd>
                </div>
                <div class="developer-kv-row">
                    <dt>Estimasi baris</dt>
                    <dd>
                        pesanan {{ $plan->estimatedRows['orders'] }} ·
                        item {{ $plan->estimatedRows['order_items'] }} ·
                        pembayaran {{ $plan->estimatedRows['payments'] }} ·
                        event {{ $plan->estimatedRows['events'] }} ·
                        riwayat status {{ $plan->estimatedRows['order_status_histories'] }} ·
                        transisi pembayaran {{ $plan->estimatedRows['payment_status_transitions'] }}
                        <br><small>Total ±{{ $plan->totalEstimatedRows() }} baris.</small>
                    </dd>
                </div>
            </dl>

            <h3 class="developer-section-title">Pengecualian simulasi (SIM-15)</h3>
            <ul>
                <li><strong>Stok tidak berubah</strong> — tidak ada reservasi, konfirmasi, atau pergerakan stok untuk data simulasi.</li>
                <li><strong>Harga item memakai harga produk saat eksekusi.</strong></li>
                <li><strong>Tanggal pembuatan produk tidak divalidasi</strong> terhadap periode simulasi.</li>
            </ul>
            <p class="developer-info-box">Semua pesanan keluar dengan status akhir (selesai/dibatalkan/kedaluwarsa) dalam satu transaksi; bila gagal di tengah, seluruh run dibatalkan dan run dicatat <em>gagal</em> (SIM-27).</p>
        </div>

        <div class="card">
            <h2 class="developer-section-title">Contoh pesanan (5 pertama)</h2>
            <div class="developer-table-wrap">
                <table class="developer-table">
                    <thead><tr><th>Pembeli</th><th>Item</th><th>Total</th><th>Status akhir</th><th>Booking (WITA)</th></tr></thead>
                    <tbody>
                    @foreach (array_slice($plan->orders, 0, 5) as $order)
                        <tr>
                            <td>{{ $accounts->get($order['user_id'])?->name ?? ('Akun #'.$order['user_id']) }}</td>
                            <td>
                                @foreach ($order['items'] as $item)
                                    {{ $products->get($item['product_id'])?->name ?? ('Produk #'.$item['product_id']) }} × {{ $item['quantity'] }}<br>
                                @endforeach
                            </td>
                            <td class="developer-cell-price">Rp{{ number_format($order['total_idr'], 0, ',', '.') }}</td>
                            <td>{{ $order['final_status'] }}</td>
                            <td class="developer-cell-nowrap">{{ $order['booking_at']->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($plan->orders) > 5)
                <p>… dan {{ count($plan->orders) - 5 }} pesanan lagi.</p>
            @endif
        </div>

        <div class="card">
            <h2 class="developer-section-title">Konfirmasi eksekusi (SIM-26)</h2>
            <p>Menekan tombol di bawah ini menjalankan simulasi <strong>sekali</strong> dengan parameter di atas. Kirim ganda dengan kunci yang sama tidak membuat run kedua; parameter yang berubah setelah pratinjau ini wajib dipratinjau ulang.</p>
            <form method="POST" action="{{ route($confirmRoute) }}" data-confirm-once>
                @csrf
                @foreach ($hiddenInputs as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <input type="hidden" name="params_hash" value="{{ $plan->paramsHash }}">
                <div class="developer-form-actions">
                    <button class="btn-primary" type="submit">Jalankan simulasi ini</button>
                    <a class="btn-secondary" href="{{ route($backRoute) }}">Kembali ubah parameter</a>
                </div>
            </form>
        </div>
    </div>
@endsection
