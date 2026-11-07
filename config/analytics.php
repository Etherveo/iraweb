<?php

// Ira Stationery — M5 analytics configuration (FR-01..06).
// Laravel sends "facts" (per WITA day per product, no personal data) to
// the internal Python analytics service (iraweb/python/analytics/,
// stdlib only) and receives structured results. The service listens on
// loopback only and requires the internal key below.

return [
    // Base URL of the Python analytics service (M5, rencana §4).
    'url' => env('ANALYTICS_URL', 'http://127.0.0.1:8001'),

    // Internal key, sent as the X-Internal-Key header. Empty by default;
    // never commit a real value. Generated locally by Ren (like
    // SIMULATOR_WEBHOOK_SECRET). The service refuses to start without
    // it, and the client refuses to call without it.
    'key' => env('ANALYTICS_INTERNAL_KEY'),

    // HTTP timeout in seconds for one analytics call.
    'timeout' => (int) env('ANALYTICS_TIMEOUT', 10),
];
