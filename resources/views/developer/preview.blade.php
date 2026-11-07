@extends('layouts.app')

@section('content')
    @php
        $orderStatusChips = [
            'selesai' => 'status-chip-success',
            'dibatalkan' => 'status-chip-danger',
            'kedaluwarsa' => 'status-chip-muted',
        ];
    @endphp
    <div class="developer-page">
        <nav class="developer-back-nav" aria-label="Breadcrumb">
            <a class="developer-back-link" href="{{ route($backRoute) }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ubah parameter</span>
            </a>
        </nav>

        <header class="developer-head">
            <div>
                <p class="developer-eyebrow">Simulator &bull; Pratinjau Rencana</p>
                <h1 class="developer-title">Pratinjau — {{ $actionLabel }}</h1>
                <p class="developer-subtitle">Periksa rencana di bawah ini sebelum menjalankan. <strong>Pratinjau ini belum menulis data apa pun</strong> (SIM-25) — data baru dibuat hanya setelah tombol "Jalankan simulasi ini" ditekan.</p>
            </div>
        </header>

        <section class="card developer-panel" aria-labelledby="preview-summary-heading">
            <h2 class="developer-section-title" id="preview-summary-heading">Ringkasan rencana</h2>
            <dl class="developer-kv">
                <div class="developer-kv-row">
                    <dt>Aksi</dt>
                    <dd><strong>{{ $actionLabel }}</strong></dd>
                </div>
                <div class="developer-kv-row">
                    <dt>Periode</dt>
                    <dd>{{ $plan->period->startDate }} s.d. {{ $plan->period->endDate }} ({{ $plan->period->days }} hari, WITA)</dd>
                </div>
                <div class="developer-kv-row">
                    <dt>Seed</dt>
                    <dd><code class="developer-code">{{ $plan->seed }}</code> (mode {{ $params['mode'] ?? 'deterministic' }})</dd>
                </div>
                <div class="developer-kv-row">
                    <dt>Akun</dt>
                    <dd>
                        <strong>{{ count($params['user_ids']) }} akun simulasi</strong>
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
                        <strong>{{ count($params['products']) }} produk</strong>
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
                    <dd><strong>{{ count($plan->orders) }} pesanan</strong> ({{ $statusBreakdown['selesai'] ?? 0 }} selesai · {{ $statusBreakdown['dibatalkan'] ?? 0 }} dibatalkan · {{ $statusBreakdown['kedaluwarsa'] ?? 0 }} kedaluwarsa)</dd>
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

            <h3 class="developer-subsection-title">Pengecualian simulasi (SIM-15)</h3>
            <ul class="developer-list">
                <li><strong>Stok tidak berubah</strong> — tidak ada reservasi, konfirmasi, atau pergerakan stok untuk data simulasi.</li>
                <li><strong>Harga item memakai harga produk saat eksekusi.</strong></li>
                <li><strong>Tanggal pembuatan produk tidak divalidasi</strong> terhadap periode simulasi.</li>
            </ul>
            <p class="developer-info-box">Semua pesanan keluar dengan status akhir (selesai/dibatalkan/kedaluwarsa) dalam satu transaksi; bila gagal di tengah, seluruh run dibatalkan dan run dicatat <em>gagal</em> (SIM-27).</p>
        </section>

        <section class="card developer-panel" aria-labelledby="preview-sample-orders-heading">
            <h2 class="developer-section-title" id="preview-sample-orders-heading">Contoh pesanan (5 pertama)</h2>
            <div class="developer-table-wrap">
                <table class="developer-table">
                    <thead>
                        <tr>
                            <th>Pembeli</th>
                            <th>Item</th>
                            <th>Total</th>
                            <th>Status akhir</th>
                            <th>Booking (WITA)</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach (array_slice($plan->orders, 0, 5) as $order)
                        <tr>
                            <td class="developer-cell-primary">{{ $accounts->get($order['user_id'])?->name ?? ('Akun #'.$order['user_id']) }}</td>
                            <td>
                                @foreach ($order['items'] as $item)
                                    {{ $products->get($item['product_id'])?->name ?? ('Produk #'.$item['product_id']) }} × {{ $item['quantity'] }}@if (! $loop->last)<br>@endif
                                @endforeach
                            </td>
                            <td class="developer-cell-price">Rp{{ number_format($order['total_idr'], 0, ',', '.') }}</td>
                            <td>
                                <span class="status-chip {{ $orderStatusChips[$order['final_status']] ?? 'status-chip-muted' }}">
                                    <span>{{ $order['final_status'] }}</span>
                                </span>
                            </td>
                            <td class="developer-cell-nowrap">{{ $order['booking_at']->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($plan->orders) > 5)
                <p class="developer-hint">… dan {{ count($plan->orders) - 5 }} pesanan lagi.</p>
            @endif
        </section>

        <section class="card developer-panel" aria-labelledby="preview-confirm-heading">
            <h2 class="developer-section-title" id="preview-confirm-heading">Konfirmasi eksekusi (SIM-26)</h2>
            <p class="developer-subtitle" style="margin:0;">Menekan tombol di bawah ini menjalankan simulasi <strong>sekali</strong> dengan parameter di atas. Kirim ganda dengan kunci yang sama tidak membuat run kedua; parameter yang berubah setelah pratinjau ini wajib dipratinjau ulang.</p>
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
        </section>
    </div>
@endsection
