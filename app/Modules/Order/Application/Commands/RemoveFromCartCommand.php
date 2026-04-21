<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Commands;

final readonly class RemoveFromCartCommand
{
    public function __construct(
        public int $userId,
        public int $cartItemId,
    ) {}
}
