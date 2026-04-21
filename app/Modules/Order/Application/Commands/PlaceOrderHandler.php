<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Commands;

use App\Modules\Order\Domain\Repository\CartRepositoryInterface;
use App\Modules\Order\Domain\Repository\OrderRepositoryInterface;
use App\Modules\Order\Infrastructure\Models\CartItem;
use App\Modules\Order\Infrastructure\Models\Order;
use App\Modules\Order\Domain\Events\OrderPlaced;
use Illuminate\Support\Facades\DB;

class PlaceOrderHandler
{
    public function __construct(
        private CartRepositoryInterface $carts,
        private OrderRepositoryInterface $orders,
    ) {}

    public function handle(PlaceOrderCommand $command): Order
    {
        $cart = $this->carts->findByUserId($command->userId);

        if (!$cart || $cart->items->isEmpty()) {
            throw new \DomainException('Cannot place order with an empty cart.');
        }

        $grandTotalItem = $cart->items->sum(fn (CartItem $item)=> $item->quantity*$item->unit_price_minor);
        $currency = $cart->items->first()->currency;
        return DB::transaction(function() use ($command, $cart, $grandTotalItem, $currency){
            $order = $this->orders->create($command->userId, $grandTotalItem, $currency);
            $grouped = $cart->items->groupBy('seller_id');

            foreach ($grouped as $sellerId => $items) {
                $subtotal = $items->sum(fn (CartItem $item) => $item->quantity * $item->unit_price_minor);

                $subOrder = $this->orders->createSubOrder($order, $sellerId, $subtotal, $currency);

                foreach ($items as $item) {
                    $this->orders->createOrderItem(
                        $order,
                        $subOrder,
                        $sellerId,
                        $item->product_id,
                        $item->quantity,
                        $item->unit_price_minor,
                        $item->quantity * $item->unit_price_minor,
                        $currency,
                    );
                }
            }

            $this->carts->clear($cart);

            event(new OrderPlaced(
                orderId: $order->id,
                userId: $command->userId,
                totalMinor: $grandTotalItem,
                currency: $currency,
                placedAt: new \DateTimeImmutable(),
            ));

            return $order->load('subOrders.items');
        });
    }
}
