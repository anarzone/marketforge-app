<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Commands;

final readonly class AddToCartCommand
{
    public function __construct(
        public int $userId,
        public int $productId,
        public int $quantity,
    ) {}
}
