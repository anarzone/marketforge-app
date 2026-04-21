<?php

use App\Providers\AppServiceProvider;
use App\Providers\CatalogServiceProvider;
use App\Providers\OrderServiceProvider;
use App\Providers\UserServiceProvider;

return [
    AppServiceProvider::class,
    CatalogServiceProvider::class,
    OrderServiceProvider::class,
    UserServiceProvider::class,
];
