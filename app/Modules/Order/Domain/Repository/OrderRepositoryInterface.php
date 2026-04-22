<?php

declare(strict_types=1);

namespace App\Modules\Order\Domain\Repository;

use App\Modules\Order\Infrastructure\Models\Order;
use App\Modules\Order\Infrastructure\Models\OrderItem;
use App\Modules\Order\Infrastructure\Models\SubOrder;
use App\Modules\SharedKernel\Domain\ValueObjects\Currency;
use Illuminate\Support\Collection;

interface OrderRepositoryInterface
{
    public function create(int $userId, int $totalMinor, Currency $currency): Order;

    public function createSubOrder(Order $order, int $sellerId, int $subtotalMinor, Currency $currency): SubOrder;

    public function createOrderItem(
        Order $order,
        SubOrder $subOrder,
        int $sellerId,
        int $productId,
        int $quantity,
        int $priceMinor,
        int $subtotalMinor,
        Currency $currency,
    ): OrderItem;

    public function findById(int $id): Order;

    /** @return Collection<int, Order> */
    public function findByUserId(int $userId): Collection;
}
