<?php

use App\Providers\AppServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\PublicStorefrontServiceProvider;

return [
    AppServiceProvider::class,
    PaymentServiceProvider::class,
    PublicStorefrontServiceProvider::class,
];
