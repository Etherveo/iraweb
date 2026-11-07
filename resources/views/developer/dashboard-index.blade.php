@extends('layouts.app')

@section('content')
    <div class="developer-page">
        <header class="developer-head">
            <div>
                <p class="developer-eyebrow">Simulator &bull; Beranda</p>
                <h1 class="developer-title">Beranda Simulator</h1>
                <p class="developer-subtitle">Panel Developer untuk data eksperimen: akun, transaksi backdate, dan funnel. Semua data di sini berlabel SIMULASI dan bisa di-reset tanpa menyentuh data toko asli.</p>
            </div>
        </header>

        <div class="developer-stat-grid">
            <div class="developer-stat-box">
                <p class="developer-stat-label">Akun simulasi</p>
                <p class="developer-stat-value">{{ $simAccountCount }}</p>
            </div>
            <div class="developer-stat-box">
                <p class="developer-stat-label">Pesanan simulasi</p>
                <p class="developer-stat-value">{{ $simOrderCount }}</p>
            </div>
            <div class="developer-stat-box">
                <p class="developer-stat-label">Event simulasi</p>
                <p class="developer-stat-value">{{ $simEventCount }}</p>
            </div>
            <div class="developer-stat-box">
                <p class="developer-stat-label">Run simulasi</p>
                <p class="developer-stat-value">{{ $runCount }}</p>
            </div>
        </div>

        <section class="card developer-panel" aria-labelledby="dev-quick-actions-heading">
            <h2 class="developer-section-title" id="dev-quick-actions-heading">Aksi cepat</h2>
            <div class="developer-form-actions">
                <a class="btn-primary" href="{{ route('developer.akun.create') }}">Buat Akun</a>
                <a class="btn-secondary" href="{{ route('developer.tx-user.create') }}">Transaksi per User</a>
                <a class="btn-secondary" href="{{ route('developer.tx-period.create') }}">Transaksi per Periode</a>
                <a class="btn-secondary" href="{{ route('developer.funnel.create') }}">Funnel</a>
            </div>
        </section>

        <section class="card developer-panel" aria-labelledby="dev-latest-run-heading">
            <h2 class="developer-section-title" id="dev-latest-run-heading">Run terakhir</h2>
            @if ($latestRun)
                <dl class="developer-kv">
                    <div class="developer-kv-row">
                        <dt>Aksi</dt>
                        <dd><strong>@include('developer.partials._action-label', ['action' => $latestRun->action])</strong></dd>
                    </div>
                    <div class="developer-kv-row">
                        <dt>Status</dt>
                        <dd>@include('developer.partials._status-chip', ['status' => $latestRun->status])</dd>
                    </div>
                    <div class="developer-kv-row">
                        <dt>Mulai</dt>
                        <dd>{{ $latestRun->started_at->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA</dd>
                    </div>
                    <div class="developer-kv-row">
                        <dt>Seed</dt>
                        <dd><code class="developer-code">{{ $latestRun->seed }}</code></dd>
                    </div>
                </dl>
                <div>
                    <a class="developer-action-link" href="{{ route('developer.run.show', $latestRun) }}">Lihat detail run &rarr;</a>
                </div>
            @else
                <div class="developer-empty">
                    <p class="developer-empty-title">Belum ada run simulasi.</p>
                    <p class="developer-hint">Jalankan aksi simulasi pertama dari tombol Aksi cepat di atas.</p>
                </div>
            @endif
        </section>

        <section class="card developer-panel" aria-labelledby="dev-recent-runs-heading">
            <h2 class="developer-section-title" id="dev-recent-runs-heading">Run terbaru</h2>
            @if ($recentRuns->isEmpty())
                <div class="developer-empty">
                    <p class="developer-empty-title">Belum ada run simulasi.</p>
                </div>
            @else
                <div class="developer-table-wrap">
                    <table class="developer-table">
                        <thead>
                            <tr>
                                <th>Run</th>
                                <th>Aksi</th>
                                <th>Status</th>
                                <th>Mulai (WITA)</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($recentRuns as $run)
                            <tr>
                                <td>
                                    <a class="developer-cell-link" href="{{ route('developer.run.show', $run) }}">
                                        <code class="developer-code">{{ substr($run->id, 0, 8) }}</code>
                                    </a>
                                </td>
                                <td class="developer-cell-primary">@include('developer.partials._action-label', ['action' => $run->action])</td>
                                <td>@include('developer.partials._status-chip', ['status' => $run->status])</td>
                                <td class="developer-cell-nowrap">{{ $run->started_at->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div>
                    <a class="developer-action-link" href="{{ route('developer.run.index') }}">Lihat semua run &rarr;</a>
                </div>
            @endif
        </section>
    </div>
@endsection
