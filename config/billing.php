<?php

return [
    // Provider: fake | stripe (por ahora 'fake')
    'provider' => env('BILLING_PROVIDER', 'fake'),

    // Precio en euros
    'plan' => [
        'professional' => [
            'amount' => 29, // euros
            'currency' => 'EUR',
        ],
    ],
];
