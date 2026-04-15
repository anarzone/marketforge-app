<?php

namespace App\Providers;

use App\Modules\Catalog\Domain\Repository\ProductRepositoryPort;
use App\Modules\Catalog\Infrastructure\Repository\CachedProductRepository;
use App\Modules\Catalog\Infrastructure\Repository\EloquentProductRepository;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ProductRepositoryPort::class, function ($app) {
            return new CachedProductRepository(
                new EloquentProductRepository,
                config('catalog.cache_ttl'),
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
