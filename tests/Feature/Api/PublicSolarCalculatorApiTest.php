<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    withFeatures(['solar_proposals' => true]);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PlnTariffSeeder']);
    RateLimiter::clear('solar-calculator:'.'127.0.0.1');
});

it('lists active PLN tariffs on the public calculator', function () {
    $response = $this->getJson('/api/v1/public/solar-calculator/tariffs');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'tariffs' => [
                    '*' => ['category_code', 'rate_per_kwh'],
                ],
            ],
        ]);

    expect(collect($response->json('data.tariffs'))->pluck('category_code')->all())
        ->toContain('B-2/TR');
});

it('calculates a recommendation from monthly_bill using B-2/TR by default', function () {
    $response = $this->postJson('/api/v1/public/solar-calculator/calculate', [
        'monthly_bill' => 15_000_000,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.input.pln_tariff.code', 'B-2/TR');

    expect((float) $response->json('data.recommendation.capacity_kwp'))->toBeGreaterThan(0);
});

it('rejects a monthly_bill below the B2B minimum', function () {
    $this->postJson('/api/v1/public/solar-calculator/calculate', [
        'monthly_bill' => 1000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('monthly_bill');
});

it('rejects a subsidized residential PLN category', function () {
    $this->postJson('/api/v1/public/solar-calculator/calculate', [
        'monthly_bill' => 15_000_000,
        'pln_category' => 'R-1/TR 450',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('pln_category');
});

it('rejects an unbounded price_per_kwp', function () {
    $this->postJson('/api/v1/public/solar-calculator/calculate', [
        'monthly_bill' => 15_000_000,
        'price_per_kwp' => 1e12,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('price_per_kwp');
});

it('returns 404 when solar_proposals is disabled', function () {
    withoutFeatures(['solar_proposals']);

    $this->postJson('/api/v1/public/solar-calculator/calculate', [
        'monthly_bill' => 15_000_000,
    ])->assertNotFound();

    $this->getJson('/api/v1/public/solar-calculator/tariffs')
        ->assertNotFound();
});

it('throttles public calculate after 10 requests per minute', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/public/solar-calculator/calculate', [
            'monthly_bill' => 15_000_000,
        ])->assertOk();
    }

    $this->postJson('/api/v1/public/solar-calculator/calculate', [
        'monthly_bill' => 15_000_000,
    ])->assertStatus(429);
});
