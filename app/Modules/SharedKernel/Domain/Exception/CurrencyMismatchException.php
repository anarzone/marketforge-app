<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Domain\Exception;

use App\Modules\SharedKernel\Domain\Currency;
use DomainException;

/**
 * Thrown when an arithmetic or comparison operation is attempted between
 * Money values in different currencies.
 *
 * Conversion is a separate, explicit operation — see CurrencyConverter.
 * See: docs/interview-questions/currency-mismatch-throw-vs-convert.md
 */
final class CurrencyMismatchException extends DomainException
{
    public static function between(Currency $a, Currency $b): self
    {
        return new self(sprintf(
            'Cannot operate on Money values with different currencies: %s and %s. '
            .'Use a CurrencyConverter with an explicit ExchangeRate to convert first.',
            $a->value,
            $b->value,
        ));
    }
}
