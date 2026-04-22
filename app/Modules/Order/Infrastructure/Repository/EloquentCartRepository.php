<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Repository;

use App\Modules\Order\Domain\Repository\CartRepositoryInterface;
use App\Modules\Order\Infrastructure\Models\Cart;
use App\Modules\Order\Infrastructure\Models\CartItem;
use App\Modules\SharedKernel\Domain\ValueObjects\Currency;

class EloquentCartRepository implements CartRepositoryInterface
{
    public function findByUserId(int $userId): ?Cart
    {
        return Cart::query()
            ->where('user_id', $userId)
            ->first();
    }

    public function createForUser(int $userId): Cart
    {
        return Cart::create(['user_id' => $userId]);
    }

    public function addItem(Cart $cart, int $productId, int $sellerId, int $quantity, int $priceMinor, Currency $currency): CartItem
    {
        return $cart->items()->create([
            'product_id' => $productId,
            'seller_id' => $sellerId,
            'quantity' => $quantity,
            'unit_price_minor' => $priceMinor,
            'currency' => $currency->value,
        ]);
    }

    public function removeItem(int $cartItemId): void
    {
        CartItem::destroy($cartItemId);
    }

    public function clear(Cart $cart): void
    {
        $cart->delete();
    }
}
