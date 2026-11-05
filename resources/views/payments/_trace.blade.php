{{-- M3c PAY-37/38/40/42. One stored HTTP trace, read-only. Expects
     $trace (PaymentHttpTrace). The JSON shown is the stored JSON,
     re-encoded for display; copy buttons copy the exact text shown
     (the same <pre> content, via [data-copy-target] in app.js).
     Everything is rendered with Blade {{ }} (escaped) — never with
     {!! !!} — so stored values cannot break out of the page. --}}
<div>
    <p><strong>{{ $trace->method }} {{ $trace->path }}</strong></p>
    <p>Waktu: {{ $trace->occurred_at?->setTimezone(config('ira.reporting_timezone'))->format('d M Y H:i:s') }} WITA — ID request: {{ $trace->request_id }}</p>
    <h4>Header request</h4>
    <p>Nilai yang sensitif sudah disembunyikan saat direkam (PAY-44); yang tampil di sini adalah nama header dan nilai yang aman.</p>
    <pre><code>{{ \App\Services\PaymentTracePanel::prettyJson($trace->request_headers_json) }}</code></pre>
    <h4>Body request (JSON)</h4>
    <pre id="trace-{{ $trace->id }}-request"><code>{{ \App\Services\PaymentTracePanel::prettyJson($trace->request_body_json) }}</code></pre>
    <button type="button" data-copy-target="trace-{{ $trace->id }}-request" data-copy-label="Salin JSON request">Salin JSON request</button>
    <h4>Response — HTTP {{ $trace->response_status }}</h4>
    <pre id="trace-{{ $trace->id }}-response"><code>{{ \App\Services\PaymentTracePanel::prettyJson($trace->response_body_json) }}</code></pre>
    <button type="button" data-copy-target="trace-{{ $trace->id }}-response" data-copy-label="Salin JSON response">Salin JSON response</button>
    @php $afterStatus = \App\Services\PaymentTracePanel::orderStatusAfter($trace); @endphp
    @if ($afterStatus)
        <p>Status pesanan setelah callback: <strong>{{ \App\Models\Order::STATUS_LABELS[$afterStatus] ?? $afterStatus }}</strong></p>
    @endif
</div>
