<?php

declare(strict_types=1);

namespace App\Modules\Order\Domain\Events;

use App\Modules\SharedKernel\Domain\Currency;

final readonly class OrderPlaced
{
    public function __construct(
        public int $orderId,
        public int $userId,
        public int $totalMinor,
        public Currency $currency,
        public \DateTimeImmutable $placedAt,
    ) {}
}