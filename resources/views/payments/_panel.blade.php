{{-- M3c PAY-37..42. Read-only "Simulated HTTP Request & Response"
     panel: it only DISPLAYS stored payment_http_traces rows and the
     payment_status_transitions timeline. It never writes anything
     (PAY-43) and has no control that changes an amount or a status
     (PAY-42). Expects $payment and $panel
     (PaymentTracePanel::forPayment). --}}
<div class="card">
    <h2>⚠ Simulated HTTP Request &amp; Response — SIMULASI</h2>
    <p><strong>SIMULASI — bukan lalu lintas gateway riil.</strong> Yang tampil di bawah ini adalah rekaman kejadian nyata yang tersimpan (bukan templat): request yang benar-benar dikirim dan response yang benar-benar diterima.</p>
    <p>Catatan: signature webhook pada simulasi ini adalah dummy, bukan signature gateway riil. Kunci signature tidak pernah ditampilkan.</p>

    @if ($panel['create'] === null && $panel['callbacks']->isEmpty())
        @if ($panel['transitions']->contains('trigger', 'panel_developer'))
            <p>Dibuat oleh panel developer — tanpa lalu lintas HTTP.</p>
        @else
            <p>Belum ada lalu lintas HTTP tercatat untuk pembayaran ini.</p>
        @endif
    @endif

    @if ($panel['create'])
        <h3>Pembuatan pembayaran</h3>
        @include('payments._trace', ['trace' => $panel['create']])
    @endif

    @if ($panel['callbacks']->isNotEmpty())
        <h3>Callback</h3>
        @foreach ($panel['callbacks'] as $callback)
            @include('payments._trace', ['trace' => $callback])
        @endforeach
        @if ($panel['curl'])
            <h4>Salin sebagai cURL</h4>
            <p>Perintah ini mengulang callback simulasi yang sama (signature HMAC sah untuk body ini; pembayaran yang sudah final dijawab "sudah diproses" tanpa efek ganda).</p>
            <pre id="trace-curl"><code>{{ $panel['curl'] }}</code></pre>
            <button type="button" data-copy-target="trace-curl" data-copy-label="Salin cURL">Salin cURL</button>
        @elseif ($panel['curlUnavailableReason'])
            <p>{{ $panel['curlUnavailableReason'] }}</p>
        @endif
    @endif

    <h3>Timeline Transisi Status</h3>
    @if ($panel['transitions']->isEmpty())
        <p>Belum ada transisi status.</p>
    @else
        <ul>
        @foreach ($panel['transitions'] as $transition)
            <li>{{ $transition->occurred_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i') }} WITA — {{ $transition->from_status ? (\App\Models\Payment::STATUS_LABELS[$transition->from_status] ?? $transition->from_status) : '—' }} → {{ \App\Models\Payment::STATUS_LABELS[$transition->to_status] ?? $transition->to_status }} (pemicu: {{ $transition->trigger }})@if ($transition->request_id) — ID request: {{ $transition->request_id }}@endif</li>
        @endforeach
        </ul>
    @endif
</div>
