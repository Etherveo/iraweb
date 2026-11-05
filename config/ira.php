<?php

// Ira Stationery — M0 configuration.
// Storage timezone stays UTC (config/app.php 'timezone'), reporting is WITA.
// See docs/blueprint.md §5 (REPORTING_TIMEZONE) and docs/PRD.md §10.1 ACC-01.
// No Python values live here: the Python analytics service is intentionally
// deferred pending Ren's script decision (M4/M5-M7 only, not M0/M1).

return [
    // WITA reporting timezone (Asia/Makassar, UTC+8).
    'reporting_timezone' => env('REPORTING_TIMEZONE', 'Asia/Makassar'),

    // Reserved simulation-account email domain (TLD .test).
    // Public buyer registration (ACC-01) rejects this domain.
    'sim_account_email_domain' => env('SIM_ACCOUNT_EMAIL_DOMAIN', 'sim.ira-stationery.test'),

    // Shared simulation-account password (SIM-08, M4 only).
    // Empty by default; never commit a real value. M1 does not use it.
    'sim_account_password' => env('SIM_ACCOUNT_PASSWORD'),

    // Payment expiry in minutes (M3b, U-09 proposal). Not used in M1.
    'payment_expiry_minutes' => (int) env('PAYMENT_EXPIRY_MINUTES', 30),
];
