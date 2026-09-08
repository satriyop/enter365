<?php

declare(strict_types=1);

use App\Domain\Pos\PosCashRounding;

it('leaves an already-round bill unchanged', function () {
    $rounding = PosCashRounding::nearest(9_200);

    expect($rounding->payable)->toBe(9_200)
        ->and($rounding->cashDue)->toBe(9_200)
        ->and($rounding->roundingAmount)->toBe(0);
});

it('rounds Air Mineral 9240 down to 9200', function () {
    $rounding = PosCashRounding::nearest(9_240);

    expect($rounding->cashDue)->toBe(9_200)
        ->and($rounding->roundingAmount)->toBe(-40);
});

it('rounds Hakau 25410 down to 25400', function () {
    $rounding = PosCashRounding::nearest(25_410);

    expect($rounding->cashDue)->toBe(25_400)
        ->and($rounding->roundingAmount)->toBe(-10);
});

it('rounds half of the unit up', function () {
    $rounding = PosCashRounding::nearest(9_250);

    expect($rounding->cashDue)->toBe(9_300)
        ->and($rounding->roundingAmount)->toBe(50);
});

it('does nothing when the unit is disabled', function () {
    $rounding = PosCashRounding::nearest(9_240, 0);

    expect($rounding->cashDue)->toBe(9_240)
        ->and($rounding->roundingAmount)->toBe(0);
});

it('none keeps cash due equal to the bill', function () {
    $rounding = PosCashRounding::none(25_410);

    expect($rounding->cashDue)->toBe(25_410)
        ->and($rounding->roundingAmount)->toBe(0);
});
