<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Point of Sale Currency Configuration
    |--------------------------------------------------------------------------
    |
    | Default currency representation for menu pricing, POS cart transactions,
    | kitchen tickets, and cash drawer reconciliations.
    |
    */
    'currency' => [
        'code' => env('POS_CURRENCY_CODE', 'PHP'),
        'symbol' => env('POS_CURRENCY_SYMBOL', '₱'),
        'precision' => 2,
    ],
];
