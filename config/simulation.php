<?php

// Ira Stationery — M4 simulator configuration (SIM-01..42).
// Bridge to the keys that already live in config/ira.php — do NOT move
// them (RegisterBuyerRequest + CheckConfigCommand already read
// config('ira.sim_account_email_domain')).

return [
    // Reserved simulation-account email domain (SIM-07).
    'email_domain' => config('ira.sim_account_email_domain'),

    // Shared simulation-account password (SIM-08). Empty by default;
    // never commit a real value.
    'account_password' => config('ira.sim_account_password'),

    // Max simulation accounts per request (SIM-03). Code constant, not
    // env (blueprint §5); must never be configurable above 100.
    'max_accounts_per_request' => 100,

    // Simulator limits (U-03, blueprint §5). Env-configurable defaults.
    'max_transactions_per_request' => (int) env('SIM_MAX_TRANSACTIONS_PER_REQUEST', 500),
    'max_events_per_request' => (int) env('SIM_MAX_EVENTS_PER_REQUEST', 2000),
    'max_period_days' => (int) env('SIM_MAX_PERIOD_DAYS', 366),

    // Biodata defaults (U-02): deterministic mode with this seed unless
    // the developer picks random mode or another seed.
    'default_mode' => env('SIM_DEFAULT_MODE', 'deterministic'),
    'default_seed' => (int) env('SIM_DEFAULT_SEED', 20261101),

    // Funnel defaults (SIM-12): view -> cart 40%, cart -> booking 60%.
    'funnel_view_to_cart' => 40,
    'funnel_cart_to_booking' => 60,

    // Final-status mix (SIM-23); must sum to 100.
    'status_mix' => ['selesai' => 80, 'dibatalkan' => 10, 'kedaluwarsa' => 10],

    // Funnel lag ranges in minutes (blueprint §9.5):
    // view -> cart, cart -> booking, booking -> paid.
    'lag_minutes' => ['view_to_cart' => [1, 30], 'cart_to_booking' => [1, 20], 'booking_to_paid' => [1, 10]],

    // Biodata generator (SIM-06): Python wrapper invoked via CLI subprocess.
    // Binary name differs per OS — laptop Ren is Windows (python),
    // the VM is Linux (python3); never hardcode one name. String kosong
    // berarti "pakai default OS": env() hanya memakai default bila variabel
    // TIDAK ADA; bila ada tapi kosong (SIM_GENERATOR_PYTHON= di .env),
    // nilainya '' dan tanpa ?: di sini Process akan mencoba menjalankan "".
    'generator_python' => env('SIM_GENERATOR_PYTHON') ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3'),
    'generator_path' => env('SIM_GENERATOR_PATH', base_path('python/biodata/generate_biodata.py')),
    'generator_timeout' => (int) env('SIM_GENERATOR_TIMEOUT', 120),
];
