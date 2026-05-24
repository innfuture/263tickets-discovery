<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\PublicStorefrontServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    PaymentServiceProvider::class,
    PublicStorefrontServiceProvider::class,
];
