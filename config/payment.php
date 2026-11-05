<?php

// M3a/M3b PAY-01/PAY-02/PAY-55/PAY-57/PAY-60: the payment-method
// catalog, defined ONCE here. Code, label ("... (Simulasi)"), display
// kind (QR or VA), VA bank code, active flag. Adding or disabling a
// method only needs a config change, never a change to the checkout
// flow. No real gateway, no provider branding (PAY-61): labels are
// generic, icons in the UI are generic.
//
// 'driver' selects the payment adapter (only 'simulator' exists;
// empty or unknown → the application fails to start, PAY-57).
// 'webhook_secret' is the dummy HMAC key for simulator webhooks
// (PAY-25/PAY-60): from the environment only, never a real value in
// this file. 'poll_seconds' is the instruction page's status polling
// interval (PAY-13).
return [
    'driver' => env('PAYMENT_DRIVER', 'simulator'),

    'webhook_secret' => env('SIMULATOR_WEBHOOK_SECRET', ''),

    'poll_seconds' => 3,

    'methods' => [
        'qris' => [
            'code' => 'qris',
            'label' => 'QRIS (Simulasi)',
            'kind' => 'qr',
            'bank_code' => null,
            'active' => true,
        ],
        'dana' => [
            'code' => 'dana',
            'label' => 'Dana (Simulasi)',
            'kind' => 'qr',
            'bank_code' => null,
            'active' => true,
        ],
        'gopay' => [
            'code' => 'gopay',
            'label' => 'GoPay (Simulasi)',
            'kind' => 'qr',
            'bank_code' => null,
            'active' => true,
        ],
        'va_bri' => [
            'code' => 'va_bri',
            'label' => 'VA BRI (Simulasi)',
            'kind' => 'va',
            'bank_code' => '01',
            'active' => true,
        ],
        'va_bca' => [
            'code' => 'va_bca',
            'label' => 'VA BCA (Simulasi)',
            'kind' => 'va',
            'bank_code' => '02',
            'active' => true,
        ],
        'va_bni' => [
            'code' => 'va_bni',
            'label' => 'VA BNI (Simulasi)',
            'kind' => 'va',
            'bank_code' => '03',
            'active' => true,
        ],
    ],
];
