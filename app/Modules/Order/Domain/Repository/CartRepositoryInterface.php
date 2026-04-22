<?php

declare(strict_types=1);

namespace App\Modules\Order\Domain\Repository;

use App\Modules\Order\Infrastructure\Models\Cart;
use App\Modules\Order\Infrastructure\Models\CartItem;
use App\Modules\SharedKernel\Domain\ValueObjects\Currency;

interface CartRepositoryInterface
{
    public function findByUserId(int $userId): ?Cart;

    public function createForUser(int $userId): Cart;

    public function addItem(Cart $cart, int $productId, int $sellerId, int $quantity, int $priceMinor, Currency $currency): CartItem;

    public function removeItem(int $cartItemId): void;

    public function clear(Cart $cart): void;
}
