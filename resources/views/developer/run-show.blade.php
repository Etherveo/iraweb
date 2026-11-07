@extends('layouts.app')

@section('content')
    @php
        $orderStatusChips = [
            'selesai' => 'status-chip-success',
            'dibatalkan' => 'status-chip-danger',
            'kedaluwarsa' => 'status-chip-muted',
        ];
        $outcomeChips = [
            'berhasil' => 'status-chip-success',
            'gagal' => 'status-chip-danger',
        ];
    @endphp
    <div class="developer-page">
        <nav class="developer-back-nav" aria-label="Breadcrumb">
            <a class="developer-back-link" href="{{ route('developer.run.index') }}">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span>Kembali ke Jejak &amp; Audit</span>
            </a>
        </nav>

        <header class="developer-head">
            <div>
                <p class="developer-eyebrow">Simulator &bull; Detail Run</p>
                <h1 class="developer-title">Detail Run</h1>
                <p class="developer-subtitle">{{ $actionLabels[$run->action] ?? $run->action }} · ID <code class="developer-code">{{ $run->id }}</code></p>
            </div>
            <div class="developer-head-actions">
                @include('developer.partials._status-chip', ['status' => $run->status])
            </div>
        </header>

        <section class="card developer-panel" aria-labelledby="run-summary-heading">
            <h2 class="developer-section-title" id="run-summary-heading">Ringkasan</h2>
            <dl class="developer-kv">
                <div class="developer-kv-row">
                    <dt>Aksi</dt>
                    <dd><strong>{{ $actionLabels[$run->action] ?? $run->action }}</strong></dd>
                </div>
                <div class="developer-kv-row">
                    <dt>Status</dt>
                    <dd>@include('developer.partials._status-chip', ['status' => $run->status])</dd>
                </div>
                <div class="developer-kv-row">
                    <dt>Seed</dt>
                    <dd><code class="developer-code">{{ $run->seed }}</code></dd>
                </div>
                <div class="developer-kv-row">
                    <dt>Periode</dt>
                    <dd>
                        @if ($run->period_start && $run->period_end)
                            {{ $run->period_start->format('d M Y') }} s.d. {{ $run->period_end->format('d M Y') }}
                        @else
                            – (aksi buat akun tidak punya periode)
                        @endif
                    </dd>
                </div>
                <div class="developer-kv-row">
                    <dt>Mulai</dt>
                    <dd>{{ $run->started_at->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                </div>
                @if ($run->finished_at)
                    <div class="developer-kv-row">
                        <dt>Selesai</dt>
                        <dd>{{ $run->finished_at->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                    </div>
                @endif
                @if ($run->reset_at)
                    <div class="developer-kv-row">
                        <dt>Direset</dt>
                        <dd>{{ $run->reset_at->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                    </div>
                @endif
                <div class="developer-kv-row">
                    <dt>Kunci idempotensi</dt>
                    <dd><code class="developer-code">{{ $run->idempotency_key }}</code></dd>
                </div>
                @if ($run->error_message)
                    <div class="developer-kv-row">
                        <dt>Pesan galat</dt>
                        <dd>{{ $run->error_message }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        <section class="card developer-panel" aria-labelledby="run-counts-heading">
            <h2 class="developer-section-title" id="run-counts-heading">Jumlah baris</h2>
            @if (! empty($run->counts))
                <dl class="developer-kv">
                    @foreach ($run->counts as $key => $value)
                        <div class="developer-kv-row">
                            <dt>{{ $key }}</dt>
                            <dd><strong>{{ $value }}</strong></dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <div class="developer-empty">
                    <p class="developer-empty-title">Run ini belum mencatat jumlah baris.</p>
                </div>
            @endif
            <p class="developer-hint">Jumlah baris terverifikasi: event {{ $eventCount }} · pembayaran {{ $paymentCount }}.</p>
        </section>

        <section class="card developer-panel" aria-labelledby="run-params-heading">
            <h2 class="developer-section-title" id="run-params-heading">Parameter</h2>
            <details class="developer-details">
                <summary>Lihat parameter lengkap (JSON)</summary>
                <pre class="developer-json">{{ json_encode($run->params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </details>
        </section>

        @if ($accounts->isNotEmpty())
            <section class="card developer-panel" aria-labelledby="run-accounts-heading">
                <h2 class="developer-section-title" id="run-accounts-heading">Akun yang dibuat ({{ $accounts->count() }})</h2>
                <div>
                    <a class="developer-action-link" href="{{ route('developer.akun-simulasi.index', ['run' => $run->id]) }}">Lihat di daftar Akun Simulasi &rarr;</a>
                </div>
                <div class="developer-table-wrap">
                    <table class="developer-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($accounts as $account)
                            <tr>
                                <td class="developer-cell-primary">{{ $account->name }}</td>
                                <td><code class="developer-code">{{ $account->email }}</code></td>
                                <td>
                                    <span class="status-chip {{ $account->is_active ? 'status-chip-success' : 'status-chip-muted' }}">
                                        <span>{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($orders->total() > 0)
            <section class="card developer-panel" aria-labelledby="run-orders-heading">
                <h2 class="developer-section-title" id="run-orders-heading">Pesanan yang dibuat ({{ $orders->total() }})</h2>
                <div class="developer-table-wrap">
                    <table class="developer-table">
                        <thead>
                            <tr>
                                <th>Nomor pesanan</th>
                                <th>Pembeli</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Dibuat (WITA)</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td class="developer-cell-nowrap"><code class="developer-code">{{ $order->order_number }}</code></td>
                                <td class="developer-cell-primary">{{ $order->user?->name ?? ('Akun #'.$order->user_id) }}</td>
                                <td class="developer-cell-price">Rp{{ number_format($order->total_idr, 0, ',', '.') }}</td>
                                <td>
                                    <span class="status-chip {{ $orderStatusChips[$order->status] ?? 'status-chip-muted' }}">
                                        <span>{{ $order->status }}</span>
                                    </span>
                                </td>
                                <td class="developer-cell-nowrap">{{ $order->created_at->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="developer-pagination">
                    {{ $orders->links() }}
                </div>
            </section>
        @endif

        <section class="card developer-panel" aria-labelledby="run-audit-heading">
            <h2 class="developer-section-title" id="run-audit-heading">Jejak audit</h2>
            @if ($run->auditLogs->isEmpty())
                <div class="developer-empty">
                    <p class="developer-empty-title">Belum ada baris audit untuk run ini.</p>
                </div>
            @else
                <div class="developer-table-wrap">
                    <table class="developer-table">
                        <thead>
                            <tr>
                                <th>Waktu (WITA)</th>
                                <th>Aksi</th>
                                <th>Hasil</th>
                                <th>Ringkasan</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($run->auditLogs as $log)
                            <tr>
                                <td class="developer-cell-nowrap">{{ $log->occurred_at->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</td>
                                <td><code class="developer-code">{{ $log->action }}</code></td>
                                <td>
                                    <span class="status-chip {{ $outcomeChips[$log->outcome] ?? 'status-chip-muted' }}">
                                        <span>{{ $log->outcome }}</span>
                                    </span>
                                </td>
                                <td><small>{{ json_encode($log->summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</small></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($run->status === 'berhasil')
            <div class="developer-form-actions">
                <a class="btn-danger" href="{{ route('developer.reset.index') }}">Reset run ini</a>
            </div>
        @endif
    </div>
@endsection
