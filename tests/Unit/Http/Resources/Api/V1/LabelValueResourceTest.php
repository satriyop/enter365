<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\LabelValueResource;
use Illuminate\Http\Request;

/**
 * Mock Enum for testing.
 */
enum MockEnum: string
{
    case Test = 'test_value';

    public function label(): string
    {
        return 'Test Label';
    }
}

/**
 * Mock Enum without label method for testing.
 */
enum MockEnumNoLabel: string
{
    case Test = 'test_value';
}

test('it transforms a backed enum with label method', function () {
    $resource = new LabelValueResource(MockEnum::Test);
    $result = $resource->toArray(new Request());

    expect($result)->toBe([
        'value' => 'test_value',
        'label' => 'Test Label',
    ]);
});

test('it transforms a backed enum without label method', function () {
    $resource = new LabelValueResource(MockEnumNoLabel::Test);
    $result = $resource->toArray(new Request());

    expect($result)->toBe([
        'value' => 'test_value',
        'label' => 'test_value', // Fallback to value
    ]);
});

test('it handles raw strings', function () {
    $resource = new LabelValueResource('plain_string');
    $result = $resource->toArray(new Request());

    expect($result)->toBe([
        'value' => 'plain_string',
        'label' => 'plain_string',
    ]);
});

test('it handles objects with value and label properties', function () {
    $obj = (object) ['value' => 'custom_v', 'label' => 'Custom L'];
    $resource = new LabelValueResource($obj);
    $result = $resource->toArray(new Request());

    expect($result)->toBe([
        'value' => 'custom_v',
        'label' => 'Custom L',
    ]);
});

test('it handles null gracefully', function () {
    $resource = new LabelValueResource(null);
    $result = $resource->toArray(new Request());

    expect($result)->toBe([
        'value' => '',
        'label' => '',
    ]);
});