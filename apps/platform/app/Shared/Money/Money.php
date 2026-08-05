<?php

declare(strict_types=1);

namespace App\Shared\Money;

use InvalidArgumentException;

/**
 * Immutable monetary value in the smallest currency unit (integer only).
 * Docs/20-architecture-technique-generale-wasplex.md #71: no float amounts.
 */
final class Money
{
    public function __construct(
        public readonly int $minorUnits,
        public readonly string $currency,
    ) {
        if ($this->currency === '') {
            throw new InvalidArgumentException('Currency is required.');
        }
    }

    public static function of(int $minorUnits, string $currency): self
    {
        return new self($minorUnits, strtoupper($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits && $this->currency === $other->currency;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot combine {$this->currency} with {$other->currency}."
            );
        }
    }
}
