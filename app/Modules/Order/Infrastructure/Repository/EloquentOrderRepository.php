<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Repository;

use App\Modules\Order\Domain\Repository\OrderRepositoryInterface;
use App\Modules\Order\Infrastructure\Models\Order;
use App\Modules\Order\Infrastructure\Models\OrderItem;
use App\Modules\Order\Infrastructure\Models\SubOrder;
use App\Modules\SharedKernel\Domain\Currency;
use Illuminate\Support\Collection;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function create(int $userId, int $totalMinor, Currency $currency): Order
    {
        return Order::create([
            'user_id' => $userId,
            'total_minor' => $totalMinor,
            'currency' => $currency,
            'placed_at' => now(),
        ]);
    }

    public function createSubOrder(Order $order, int $sellerId, int $subtotalMinor, Currency $currency): SubOrder
    {
        return $order->subOrders()->create([
            'seller_id' => $sellerId,
            'subtotal_minor' => $subtotalMinor,
            'currency' => $currency,
        ]);
    }

    public function createOrderItem(
        Order $order,
        SubOrder $subOrder,
        int $sellerId,
        int $productId,
        int $quantity,
        int $priceMinor,
        int $subtotalMinor,
        Currency $currency,
    ): OrderItem {
        return OrderItem::create([
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'seller_id' => $sellerId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price_minor' => $priceMinor,
            'subtotal_minor' => $subtotalMinor,
            'currency' => $currency,
        ]);
    }

    public function findById(int $id): Order
    {
        return Order::query()->findOrFail($id);
    }

    /** @return Collection<int, Order> */
    public function findByUserId(int $userId): Collection
    {
        return Order::where('user_id', $userId)->get();
    }
}
