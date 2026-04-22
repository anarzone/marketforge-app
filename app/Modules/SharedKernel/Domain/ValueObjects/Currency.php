<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Domain;

enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case JPY = 'JPY';

    /**
     * Number of fractional digits per ISO 4217.
     *
     * USD/EUR have 2 (cents). JPY has 0 (no fractional unit — the smallest
     * unit IS the yen). The exponent lives on Currency, not in a Money
     * lookup table, because the knowledge of "how many decimals does this
     * currency have" belongs to the currency itself.
     *
     * See: docs/interview-questions/enums-vs-strings-for-domain-types.md
     */
    public function exponent(): int
    {
        return match ($this) {
            self::USD, self::EUR => 2,
            self::JPY => 0,
        };
    }
}
