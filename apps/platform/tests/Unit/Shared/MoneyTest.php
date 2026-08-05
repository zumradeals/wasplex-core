<?php

declare(strict_types=1);

use App\Shared\Money\Money;

test('money adds and subtracts within the same currency', function (): void {
    $a = Money::of(1_000, 'XOF');
    $b = Money::of(250, 'XOF');

    expect($a->add($b)->minorUnits)->toBe(1_250);
    expect($a->subtract($b)->minorUnits)->toBe(750);
});

test('money refuses to combine different currencies', function (): void {
    $xof = Money::of(1_000, 'XOF');
    $eur = Money::of(1_000, 'EUR');

    $xof->add($eur);
})->throws(InvalidArgumentException::class);

test('money equality is based on amount and currency', function (): void {
    expect(Money::of(500, 'XOF')->equals(Money::of(500, 'XOF')))->toBeTrue();
    expect(Money::of(500, 'XOF')->equals(Money::of(500, 'EUR')))->toBeFalse();
});

test('money cannot be constructed from a float', function (): void {
    // @phpstan-ignore-next-line — intentional type violation under test.
    new Money(10.5, 'XOF');
})->throws(TypeError::class);

test('a negative money amount is detected', function (): void {
    expect(Money::of(-100, 'XOF')->isNegative())->toBeTrue();
    expect(Money::of(100, 'XOF')->isNegative())->toBeFalse();
});
