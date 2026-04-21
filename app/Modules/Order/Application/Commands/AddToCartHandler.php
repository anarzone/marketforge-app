<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Commands;

use App\Modules\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Modules\Order\Domain\Repository\CartRepositoryInterface;
use App\Modules\Order\Infrastructure\Models\CartItem;

class AddToCartHandler
{
    public function __construct(
        private CartRepositoryInterface $carts,
        private ProductRepositoryInterface $products,
    ) {}

    public function handle(AddToCartCommand $command): CartItem
    {
        $userId = $command->userId;

        $product = $this->products->findActiveById($command->productId);
        $cart = $this->carts->findByUserId($userId) ?? $this->carts->createForUser($userId);
        return $this->carts->addItem($cart, $product->id, $product->seller_id, $command->quantity, $product->price_minor, $product->currency);
    }
}
