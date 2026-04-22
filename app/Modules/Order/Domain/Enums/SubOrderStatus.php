<?php

declare(strict_types=1);

namespace App\Modules\Order\Domain;

enum SubOrderStatus: string
{
    case Placed = 'placed';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Refunded = 'refunded';
    case PaymentFailed = 'payment_failed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Placed => [self::Paid, self::PaymentFailed, self::Cancelled],
            self::Paid => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped],
            self::Shipped => [self::Delivered],
            self::Delivered => [self::Completed, self::Refunded],
            self::Completed, self::Refunded, self::PaymentFailed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function transitionTo(self $target): self
    {
        if (! $this->canTransitionTo($target)) {
            throw new \DomainException(
                "Cannot transition from {$this->value} to {$target->value}"
            );
        }

        return $target;
    }
}
