<?php

use App\Services\Payments\ShanonoPayService;

return [
     'gateway' => env('PAYMENT_GATEWAY', 'mock'),
];
