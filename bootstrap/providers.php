<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\StripeServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    StripeServiceProvider::class,
];
