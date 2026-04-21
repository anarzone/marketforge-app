<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Commands;

final readonly class PlaceOrderCommand
{
    public function __construct(
        public int $userId,
    ) {}
}
