<?php

namespace App\Providers;

use App\Modules\Catalog\Domain\Repository\ProductRepositoryInterface;
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
        $this->app->bind(ProductRepositoryInterface::class, function ($app) {
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
