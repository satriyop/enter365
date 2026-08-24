<?php

declare(strict_types=1);

use App\Models\Inventory\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);

    Product::factory()->create([
        'name' => 'MCB 16A',
        'sku' => 'MCB-16A',
        'barcode' => null,
    ]);
});

describe('QueryFilter request dispatch is restricted', function () {
    it('does not 500 when the request names a QueryFilter infrastructure method', function (string $parameter) {
        $this->getJson('/api/v1/products?'.$parameter.'=1')->assertOk();
    })->with([
        'apply',
        'apply_sorting',
        'should_apply_filter',
        'get_request',
        'get_builder',
        'get_filterable_parameters',
        'get_allowed_includes',
        'get_default_sort_field',
    ]);

    it('does not 500 on an invalid sort direction', function () {
        $this->getJson('/api/v1/products?sort=name&direction='.urlencode('DROP TABLE products'))
            ->assertOk();
    });

    it('still honours a legitimate product filter', function () {
        $this->getJson('/api/v1/products?search=MCB')
            ->assertOk()
            ->assertJsonFragment(['name' => 'MCB 16A']);
    });

    it('keyword search does not throw on sqlite', function () {
        $this->getJson('/api/v1/products?keyword=MCB')
            ->assertOk()
            ->assertJsonFragment(['name' => 'MCB 16A']);
    });

    it('keyword search with regex metacharacters does not 500', function () {
        $this->getJson('/api/v1/products?keyword='.urlencode('(MCB)'))->assertOk();
    });
});
