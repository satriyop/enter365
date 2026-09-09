<?php

declare(strict_types=1);

use Database\Seeders\PlnTariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlnTariffSeeder::class);
});

it('returns active tariffs', function (): void {
    $response = $this->getJson('/api/v1/public/solar-calculator/tariffs')
        ->assertOk();

    $tariffs = $response->json('data.tariffs');

    expect($tariffs)->toBeArray()
        ->and($tariffs)->not->toBeEmpty()
        ->and($tariffs[0])->toHaveKeys([
            'category_code',
            'category_name',
            'customer_type',
            'power_va_min',
            'power_va_max',
            'rate_per_kwh',
        ]);
});

it('calculates a B-2/TR quote for a 15 million bill', function (): void {
    $response = $this->postJson('/api/v1/public/solar-calculator/calculate', [
        'monthly_bill' => 15_000_000,
    ])->assertOk();

    $data = $response->json('data');

    expect($data['recommendation']['capacity_kwp'])->toBeGreaterThan(0)
        ->and($data['input']['pln_tariff']['code'])->toBe('B-2/TR')
        ->and($data['savings']['monthly_savings'])->toBeGreaterThan(0);
});

it('rejects a bill below five million', function (): void {
    $this->postJson('/api/v1/public/solar-calculator/calculate', [
        'monthly_bill' => 1000,
    ])->assertUnprocessable();
});

it('rejects a subsidized residential category', function (): void {
    $this->postJson('/api/v1/public/solar-calculator/calculate', [
        'monthly_bill' => 15_000_000,
        'pln_category' => 'R-1/TR 450',
    ])->assertUnprocessable();
});

it('rejects an unbounded price per kWp', function (): void {
    $this->postJson('/api/v1/public/solar-calculator/calculate', [
        'monthly_bill' => 15_000_000,
        'price_per_kwp' => 1e12,
    ])->assertUnprocessable();
});

it('caps recommended kWp to 80 percent of the connection', function (): void {
    $response = $this->postJson('/api/v1/public/solar-calculator/calculate', [
        'monthly_bill' => 15_000_000,
        'pln_power_va' => 5500,
    ])->assertOk();

    expect($response->json('data.recommendation.capacity_kwp'))->toBeLessThanOrEqual(4.4);
});

it('returns 404 when solar proposals are disabled', function (): void {
    withoutFeatures(['solar_proposals']);

    $this->getJson('/api/v1/public/solar-calculator/tariffs')
        ->assertNotFound();
});
