<?php

declare(strict_types=1);

use App\Enums\ContactAddressRole;

it('exposes invoice delivery and contact address roles', function () {
    expect(ContactAddressRole::cases())->toHaveCount(3)
        ->and(ContactAddressRole::Invoice->value)->toBe('invoice')
        ->and(ContactAddressRole::Delivery->value)->toBe('delivery')
        ->and(ContactAddressRole::Contact->value)->toBe('contact');
});
