<?php

// M3a PAY-01/PAY-02: the payment-method catalog, defined ONCE here.
// Code, label ("... (Simulasi)"), display kind (QR or VA), VA bank code,
// active flag. Adding or disabling a method only needs a config change,
// never a change to the checkout flow. No real gateway, no provider
// branding (PAY-61): labels are generic, icons in the UI are generic.
// qr_url / QR rendering / VA display on the instruction page are M3b.
return [
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
