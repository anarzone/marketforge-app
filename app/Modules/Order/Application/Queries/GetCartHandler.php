<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Queries;

use App\Modules\Order\Domain\Repository\CartRepositoryInterface;
use App\Modules\Order\Infrastructure\Models\Cart;

class GetCartHandler
{
    public function __construct(
        private CartRepositoryInterface $carts,
    ) {}

    public function handle(GetCartQuery $query): ?Cart
    {
        return $this->carts->findByUserId($query->userId)?->load('items');
    }
}
