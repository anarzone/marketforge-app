<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Domain;

use App\Modules\SharedKernel\Domain\Exception\CurrencyMismatchException;
use InvalidArgumentException;

final readonly class Money
{
    private function __construct(
        private int $minorAmount,
        private Currency $currency,
    ) {}

    // -- Construction --------------------------------------------------------

    public static function ofMinor(int $minorAmount, Currency $currency): self
    {
        return new self($minorAmount, $currency);
    }

    /**
     * Parse a major-unit decimal string into minor units without floats.
     *
     * "19.99" + EUR → 1999.  "100" + JPY → 100.  "0.1" + EUR → 10.
     */
    public static function ofMajor(string $major, Currency $currency): self
    {
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $major, $matches) !== 1) {
            throw new InvalidArgumentException("Invalid money format: \"{$major}\"");
        }

        $sign = $matches[1] === '-' ? -1 : 1;
        $whole = $matches[2];
        $fraction = $matches[3] ?? '';
        $exponent = $currency->exponent();

        if ($exponent === 0 && $fraction !== '') {
            throw new InvalidArgumentException("{$currency->value} does not support fractional units");
        }

        if (strlen($fraction) > $exponent) {
            throw new InvalidArgumentException(
                "Too many fractional digits for {$currency->value}: \"{$major}\" (max {$exponent})"
            );
        }

        $fraction = str_pad($fraction, $exponent, '0', STR_PAD_RIGHT);
        $minorAmount = (int) ($whole.$fraction) * $sign;

        return new self($minorAmount, $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    // -- Accessors -----------------------------------------------------------

    public function minorAmount(): int
    {
        return $this->minorAmount;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    /** Convert minor units back to a major-unit string: 1999 + EUR → "19.99". */
    public function toMajor(): string
    {
        $exponent = $this->currency->exponent();

        if ($exponent === 0) {
            return (string) $this->minorAmount;
        }

        $abs = abs($this->minorAmount);
        $padded = str_pad((string) $abs, $exponent + 1, '0', STR_PAD_LEFT);
        $insertAt = strlen($padded) - $exponent;
        $result = substr($padded, 0, $insertAt).'.'.substr($padded, $insertAt);
        $result = ltrim($result, '0') ?: '0';

        if (str_starts_with($result, '.')) {
            $result = '0'.$result;
        }

        return $this->minorAmount < 0 ? '-'.$result : $result;
    }

    // -- Arithmetic ----------------------------------------------------------

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorAmount + $other->minorAmount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorAmount - $other->minorAmount, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->minorAmount * $factor, $this->currency);
    }

    // -- Comparison -------------------------------------------
    //
    // ---------------

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency
            && $this->minorAmount === $other->minorAmount;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minorAmount > $other->minorAmount;
    }

    public function isZero(): bool
    {
        return $this->minorAmount === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorAmount < 0;
    }

    // -- Display -------------------------------------------------------------

    public function __toString(): string
    {
        return $this->toMajor().' '.$this->currency->value;
    }

    // -- Internal ------------------------------------------------------------

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatchException::between($this->currency, $other->currency);
        }
    }
}
