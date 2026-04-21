<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Order\Domain\Repository\CartRepositoryInterface;
use App\Modules\Order\Domain\Repository\OrderRepositoryInterface;
use App\Modules\Order\Infrastructure\Repository\EloquentCartRepository;
use App\Modules\Order\Infrastructure\Repository\EloquentOrderRepository;
use Illuminate\Support\ServiceProvider;

class OrderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CartRepositoryInterface::class, EloquentCartRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
