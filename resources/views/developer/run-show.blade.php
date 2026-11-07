@extends('layouts.app')

@section('content')
    <div class="developer-page">
        <div class="developer-head">
            <div>
                <p class="developer-eyebrow">Simulator</p>
                <h1 class="developer-title">Detail Run</h1>
                <p class="developer-subtitle">{{ $actionLabels[$run->action] ?? $run->action }} · ID <code>{{ $run->id }}</code></p>
            </div>
            <div>
                @include('developer.partials._status-chip', ['status' => $run->status])
            </div>
        </div>

        <div class="card">
            <h2 class="developer-section-title">Ringkasan</h2>
            <dl class="developer-kv">
                <div class="developer-kv-row">
                    <dt>Aksi</dt>
                    <dd>{{ $actionLabels[$run->action] ?? $run->action }}</dd>
                </div>
                <div class="developer-kv-row">
                    <dt>Status</dt>
                    <dd>@include('developer.partials._status-chip', ['status' => $run->status])</dd>
                </div>
                <div class="developer-kv-row">
                    <dt>Seed</dt>
                    <dd>{{ $run->seed }}</dd>
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
                    <dd><code>{{ $run->idempotency_key }}</code></dd>
                </div>
                @if ($run->error_message)
                    <div class="developer-kv-row">
                        <dt>Pesan galat</dt>
                        <dd>{{ $run->error_message }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="card">
            <h2 class="developer-section-title">Jumlah baris</h2>
            @if (! empty($run->counts))
                <dl class="developer-kv">
                    @foreach ($run->counts as $key => $value)
                        <div class="developer-kv-row">
                            <dt>{{ $key }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p>Run ini belum mencatat jumlah baris.</p>
            @endif
            <p class="developer-hint">Jumlah baris terverifikasi: event {{ $eventCount }} · pembayaran {{ $paymentCount }}.</p>
        </div>

        <div class="card">
            <h2 class="developer-section-title">Parameter</h2>
            <details>
                <summary>Lihat parameter lengkap (JSON)</summary>
                <pre class="developer-json">{{ json_encode($run->params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </details>
        </div>

        @if ($accounts->isNotEmpty())
            <div class="card">
                <h2 class="developer-section-title">Akun yang dibuat ({{ $accounts->count() }})</h2>
                <p><a href="{{ route('developer.akun-simulasi.index', ['run' => $run->id]) }}">Lihat di daftar Akun Simulasi</a></p>
                <div class="developer-table-wrap">
                    <table class="developer-table">
                        <thead><tr><th>Nama</th><th>Email</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($accounts as $account)
                            <tr>
                                <td class="developer-cell-primary">{{ $account->name }}</td>
                                <td>{{ $account->email }}</td>
                                <td>{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($orders->total() > 0)
            <div class="card">
                <h2 class="developer-section-title">Pesanan yang dibuat ({{ $orders->total() }})</h2>
                <div class="developer-table-wrap">
                    <table class="developer-table">
                        <thead><tr><th>Nomor pesanan</th><th>Pembeli</th><th>Total</th><th>Status</th><th>Dibuat (WITA)</th></tr></thead>
                        <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td class="developer-cell-nowrap">{{ $order->order_number }}</td>
                                <td>{{ $order->user?->name ?? ('Akun #'.$order->user_id) }}</td>
                                <td class="developer-cell-price">Rp{{ number_format($order->total_idr, 0, ',', '.') }}</td>
                                <td>{{ $order->status }}</td>
                                <td class="developer-cell-nowrap">{{ $order->created_at->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $orders->links() }}
            </div>
        @endif

        <div class="card">
            <h2 class="developer-section-title">Jejak audit</h2>
            @if ($run->auditLogs->isEmpty())
                <p>Belum ada baris audit untuk run ini.</p>
            @else
                <div class="developer-table-wrap">
                    <table class="developer-table">
                        <thead><tr><th>Waktu (WITA)</th><th>Aksi</th><th>Hasil</th><th>Ringkasan</th></tr></thead>
                        <tbody>
                        @foreach ($run->auditLogs as $log)
                            <tr>
                                <td class="developer-cell-nowrap">{{ $log->occurred_at->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</td>
                                <td>{{ $log->action }}</td>
                                <td>{{ $log->outcome }}</td>
                                <td><small>{{ json_encode($log->summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</small></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($run->status === 'berhasil')
            <div class="developer-form-actions">
                <a class="btn-danger" href="{{ route('developer.reset.index') }}">Reset run ini</a>
            </div>
        @endif
    </div>
@endsection
