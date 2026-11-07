@extends('layouts.app')

@section('content')
    <div class="developer-page">
        <div class="developer-head">
            <div>
                <p class="developer-eyebrow">Simulator</p>
                <h1 class="developer-title">Jejak &amp; Audit</h1>
                <p class="developer-subtitle">Daftar run simulasi (SIM-37) — satu baris per aksi. Audit log bersifat append-only dan tidak bisa diubah atau dihapus, termasuk oleh reset (SIM-36).</p>
            </div>
        </div>

        <div class="card">
            <form class="developer-filters" method="GET" action="{{ route('developer.run.index') }}">
                <div class="developer-filter-group">
                    <label class="developer-label" for="filter-action">Jenis aksi</label>
                    <select class="input" id="filter-action" name="action">
                        <option value="">Semua aksi</option>
                        @foreach ($actionLabels as $value => $label)
                            <option value="{{ $value }}" {{ $filters['action'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="developer-filter-group">
                    <label class="developer-label" for="filter-status">Status</label>
                    <select class="input" id="filter-status" name="status">
                        <option value="">Semua status</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" {{ $filters['status'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="developer-filter-group">
                    <label class="developer-label" for="filter-date">Tanggal mulai</label>
                    <input class="input" type="date" id="filter-date" name="date" value="{{ $filters['date'] }}">
                </div>
                <div class="developer-filter-actions">
                    <button class="btn-primary" type="submit">Filter</button>
                    <a class="btn-secondary" href="{{ route('developer.run.index') }}">Reset filter</a>
                </div>
            </form>

            @if ($runs->isEmpty())
                <p>Belum ada run yang cocok dengan filter.</p>
            @else
                <div class="developer-table-wrap">
                    <table class="developer-table">
                        <thead><tr><th>Run</th><th>Aksi</th><th>Status</th><th>Seed</th><th>Periode</th><th>Mulai (WITA)</th><th>Hasil</th></tr></thead>
                        <tbody>
                        @foreach ($runs as $run)
                            <tr>
                                <td><a href="{{ route('developer.run.show', $run) }}">{{ substr($run->id, 0, 8) }}</a></td>
                                <td>{{ $actionLabels[$run->action] ?? $run->action }}</td>
                                <td>@include('developer.partials._status-chip', ['status' => $run->status])</td>
                                <td>{{ $run->seed }}</td>
                                <td class="developer-cell-nowrap">
                                    @if ($run->period_start && $run->period_end)
                                        {{ $run->period_start->format('d M Y') }} – {{ $run->period_end->format('d M Y') }}
                                    @else
                                        –
                                    @endif
                                </td>
                                <td class="developer-cell-nowrap">{{ $run->started_at->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</td>
                                <td>
                                    @if ($run->status === 'gagal' && $run->error_message)
                                        <small>{{ $run->error_message }}</small>
                                    @elseif (! empty($run->counts))
                                        <small>
                                            @foreach ($run->counts as $key => $value)
                                                {{ $key }}: {{ $value }}@if (! $loop->last), @endif
                                            @endforeach
                                        </small>
                                    @else
                                        –
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $runs->links() }}
            @endif
        </div>
    </div>
@endsection
