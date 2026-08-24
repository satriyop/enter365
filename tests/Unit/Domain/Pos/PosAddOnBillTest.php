<?php

declare(strict_types=1);

use App\Domain\Pos\PosAddOnBill;

it('charges Hakau cafe 22000 as 25410 after 5% service and 10% PBJT', function () {
    $bill = PosAddOnBill::of(22_000, 5, 10);

    expect($bill->subtotal)->toBe(22_000)
        ->and($bill->serviceAmount)->toBe(1_100)
        ->and($bill->taxAmount)->toBe(2_310)
        ->and($bill->payable)->toBe(25_410);
});

it('charges Kopi O cafe 15000 as 17325', function () {
    $bill = PosAddOnBill::of(15_000, 5, 10);

    expect($bill->subtotal)->toBe(15_000)
        ->and($bill->serviceAmount)->toBe(750)
        ->and($bill->taxAmount)->toBe(1_575)
        ->and($bill->payable)->toBe(17_325);
});

it('charges pastry cafe 28000 as 32340', function () {
    $bill = PosAddOnBill::of(28_000, 5, 10);

    expect($bill->payable)->toBe(32_340);
});

it('applies rates on the header subtotal, not per line', function () {
    $bill = PosAddOnBill::of(22_000 + 15_000, 5, 10);

    expect($bill->subtotal)->toBe(37_000)
        ->and($bill->serviceAmount)->toBe(1_850)
        ->and($bill->taxAmount)->toBe(3_885)
        ->and($bill->payable)->toBe(42_735);
});
