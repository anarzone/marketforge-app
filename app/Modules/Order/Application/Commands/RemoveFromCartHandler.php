<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Commands;

use App\Modules\Order\Domain\Repository\CartRepositoryInterface;

class RemoveFromCartHandler
{
    public function __construct(
        private CartRepositoryInterface $carts,
    ) {}

    /**
     * TODO (Anar): implement
     *
     * Just delegate to the repository. Simple.
     */
    public function handle(RemoveFromCartCommand $command): void
    {
        $this->carts->removeItem($command->cartItemId);
    }
}
