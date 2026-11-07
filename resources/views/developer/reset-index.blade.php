@extends('layouts.app')

@section('content')
    <div class="developer-page">
        <header class="developer-head">
            <div>
                <p class="developer-eyebrow">Simulator &bull; Data</p>
                <h1 class="developer-title">Reset Data Simulasi</h1>
                <p class="developer-subtitle">Hapus data simulasi tanpa menyentuh data toko asli (SIM-38/39/40). Setiap penghapusan dibatasi ke <code>origin=developer_simulation</code>; audit log tidak pernah dihapus (SIM-36).</p>
            </div>
        </header>

        @if ($runningExists)
            <p class="developer-info-box developer-info-box--warn"><strong>Ada run yang masih berjalan</strong> — reset ditunda sampai run selesai, supaya data run yang sedang berjalan tidak terhapus di tengah jalan.</p>
        @endif

        @if (session('reset_summary'))
            @php($summary = session('reset_summary'))
            <section class="card developer-panel" aria-labelledby="reset-summary-heading">
                <h2 class="developer-section-title" id="reset-summary-heading">Hasil reset terakhir</h2>
                <dl class="developer-kv">
                    <div class="developer-kv-row">
                        <dt>Cakupan</dt>
                        <dd><strong>{{ $summary['scope'] === 'all' ? 'Seluruh data simulasi' : 'Run '.$summary['run_id'] }}</strong></dd>
                    </div>
                    @foreach ($summary['deleted'] as $table => $count)
                        <div class="developer-kv-row">
                            <dt><code class="developer-code">{{ $table }}</code></dt>
                            <dd>{{ $count }} baris dihapus</dd>
                        </div>
                    @endforeach
                    <div class="developer-kv-row">
                        <dt>Akun dihapus</dt>
                        <dd>{{ count($summary['accounts_deleted']) }} akun</dd>
                    </div>
                    <div class="developer-kv-row">
                        <dt>Akun dinonaktifkan</dt>
                        <dd>
                            @if ($summary['accounts_deactivated'] === [])
                                Tidak ada — semua akun dalam cakupan terhapus bersih.
                            @else
                                {{ count($summary['accounts_deactivated']) }} akun masih punya data di luar cakupan reset, jadi dinonaktifkan (bukan dihapus):
                                @foreach ($summary['accounts_deactivated'] as $account)
                                    <br><small>{{ $account['name'] }} ({{ $account['email'] }})</small>
                                @endforeach
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>
        @endif

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
                <p class="developer-stat-label">Pembayaran simulasi</p>
                <p class="developer-stat-value">{{ $simPaymentCount }}</p>
            </div>
        </div>

        <section class="card developer-panel developer-card-danger" aria-labelledby="reset-all-heading">
            <h2 class="developer-section-title" id="reset-all-heading">Reset seluruh data simulasi</h2>
            <p class="developer-subtitle" style="margin:0;">Menghapus <strong>semua</strong> data simulasi: akun, pesanan, item, pembayaran, event, dan riwayatnya. Jumlah baris data normal (origin <code>normal</code>) identik sebelum dan sesudah reset — diuji di SimulationM4Test (SIM-39). Audit log tetap ada.</p>
            <form class="developer-form" method="POST" action="{{ route('developer.reset.all') }}" data-reset-form>
                @csrf
                <div class="developer-field">
                    <label class="developer-label" for="phrase-all">Ketik frasa konfirmasi</label>
                    <input class="input" type="text" id="phrase-all" name="phrase" placeholder="HAPUS SEMUA SIMULASI" autocomplete="off" data-reset-phrase="HAPUS SEMUA SIMULASI" {{ $runningExists ? 'disabled' : '' }}
                           @error('phrase') aria-invalid="true" @enderror>
                    <p class="developer-hint">Tombol aktif hanya bila frasa <code>HAPUS SEMUA SIMULASI</code> diketik persis.</p>
                    @error('phrase')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="developer-form-actions">
                    <button class="btn-danger" type="submit" data-reset-submit {{ $runningExists ? 'disabled' : '' }}>Hapus semua data simulasi</button>
                </div>
            </form>
        </section>

        <section class="card developer-panel" aria-labelledby="reset-per-run-heading">
            <h2 class="developer-section-title" id="reset-per-run-heading">Reset per run</h2>
            <p class="developer-subtitle" style="margin:0;">Hanya run berstatus <em>berhasil</em> yang bisa direset. Akun dari run itu dihapus hanya bila tidak punya data di luar run ini; bila masih punya, akun dinonaktifkan dan dilaporkan (SIM-38).</p>
            @if ($resettableRuns->isEmpty())
                <div class="developer-empty">
                    <p class="developer-empty-title">Tidak ada run yang bisa direset.</p>
                    <p class="developer-hint">Run yang berstatus berhasil akan muncul di daftar ini.</p>
                </div>
            @else
                <div class="developer-table-wrap">
                    <table class="developer-table">
                        <thead>
                            <tr>
                                <th>Run</th>
                                <th>Aksi</th>
                                <th>Mulai (WITA)</th>
                                <th>Hasil</th>
                                <th>Konfirmasi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($resettableRuns as $run)
                            <tr>
                                <td>
                                    <a class="developer-cell-link" href="{{ route('developer.run.show', $run) }}">
                                        <code class="developer-code">{{ substr($run->id, 0, 8) }}</code>
                                    </a>
                                </td>
                                <td class="developer-cell-primary">@include('developer.partials._action-label', ['action' => $run->action])</td>
                                <td class="developer-cell-nowrap">{{ $run->started_at->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }}</td>
                                <td>
                                    @if (! empty($run->counts))
                                        <small>
                                            @foreach ($run->counts as $key => $value)
                                                {{ $key }}: <strong>{{ $value }}</strong>@if (! $loop->last), @endif
                                            @endforeach
                                        </small>
                                    @else
                                        –
                                    @endif
                                </td>
                                <td>
                                    <form class="developer-inline-reset" method="POST" action="{{ route('developer.reset.run', $run) }}" data-reset-form>
                                        @csrf
                                        <input class="input" type="text" name="phrase" placeholder="HAPUS RUN" autocomplete="off" aria-label="Frasa konfirmasi reset run {{ substr($run->id, 0, 8) }}" data-reset-phrase="HAPUS RUN" {{ $runningExists ? 'disabled' : '' }}>
                                        <button class="btn-danger" type="submit" data-reset-submit {{ $runningExists ? 'disabled' : '' }}>Reset run ini</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="developer-pagination">
                    {{ $resettableRuns->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
