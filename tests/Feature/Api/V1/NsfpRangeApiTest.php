<?php

declare(strict_types=1);

use App\Models\Tax\NsfpRange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Authenticate as admin (has all permissions)
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('NsfpRange API CRUD', function () {
    it('lists NSFP ranges', function () {
        NsfpRange::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/nsfp-ranges');

        $response->assertSuccessful();
        $response->assertJsonCount(3, 'data');
    });

    it('filters by year code', function () {
        NsfpRange::factory()->forYear('25')->create();
        NsfpRange::factory()->forYear('26')->create();

        $response = $this->getJson('/api/v1/nsfp-ranges?year_code=26');

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
    });

    it('filters by active status', function () {
        NsfpRange::factory()->active()->create();
        NsfpRange::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/nsfp-ranges?is_active=1');

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
    });

    it('creates a new NSFP range', function () {
        $response = $this->postJson('/api/v1/nsfp-ranges', [
            'transaction_code' => '010',
            'branch_code' => '000',
            'year_code' => '26',
            'range_start' => 1,
            'range_end' => 1000,
            'description' => 'Range e-Nofa Januari 2026',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.transaction_code', '010');
        $response->assertJsonPath('data.range_start', 1);
        $response->assertJsonPath('data.range_end', 1000);
        $response->assertJsonPath('data.next_number', 1);
        $response->assertJsonPath('data.capacity', 1000);

        $this->assertDatabaseHas('nsfp_ranges', [
            'transaction_code' => '010',
            'range_start' => 1,
            'range_end' => 1000,
        ]);
    });

    it('validates required fields on create', function () {
        $response = $this->postJson('/api/v1/nsfp-ranges', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'transaction_code', 'branch_code', 'year_code',
            'range_start', 'range_end',
        ]);
    });

    it('validates code format on create', function () {
        $response = $this->postJson('/api/v1/nsfp-ranges', [
            'transaction_code' => 'ABC', // Must be digits
            'branch_code' => '00',       // Must be 3 chars
            'year_code' => '2026',       // Must be 2 chars
            'range_start' => 1,
            'range_end' => 1000,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'transaction_code', 'branch_code', 'year_code',
        ]);
    });

    it('validates range_end must be greater than range_start', function () {
        $response = $this->postJson('/api/v1/nsfp-ranges', [
            'transaction_code' => '010',
            'branch_code' => '000',
            'year_code' => '26',
            'range_start' => 1000,
            'range_end' => 500,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['range_end']);
    });

    it('prevents duplicate range creation', function () {
        NsfpRange::factory()->create([
            'transaction_code' => '010',
            'branch_code' => '000',
            'year_code' => '26',
            'range_start' => 1,
            'range_end' => 1000,
        ]);

        $response = $this->postJson('/api/v1/nsfp-ranges', [
            'transaction_code' => '010',
            'branch_code' => '000',
            'year_code' => '26',
            'range_start' => 1,
            'range_end' => 2000,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['range_start']);
    });

    it('shows a single NSFP range with computed fields', function () {
        $range = NsfpRange::factory()->withRange(1, 1000)->forYear('26')->create();

        $response = $this->getJson("/api/v1/nsfp-ranges/{$range->id}");

        $response->assertSuccessful();
        $response->assertJsonPath('data.id', $range->id);
        $response->assertJsonPath('data.capacity', 1000);
        $response->assertJsonPath('data.remaining', 1000);
        $response->assertJsonPath('data.is_exhausted', false);
        $response->assertJsonPath('data.formatted_range_start', '010.000-26.00000001');
        $response->assertJsonPath('data.formatted_range_end', '010.000-26.00001000');
    });

    it('updates description and active status', function () {
        $range = NsfpRange::factory()->create();

        $response = $this->putJson("/api/v1/nsfp-ranges/{$range->id}", [
            'description' => 'Updated description',
            'is_active' => false,
        ]);

        $response->assertSuccessful();
        expect($range->fresh()->description)->toBe('Updated description');
        expect($range->fresh()->is_active)->toBeFalse();
    });

    it('deactivates a range', function () {
        $range = NsfpRange::factory()->active()->create();

        $response = $this->postJson("/api/v1/nsfp-ranges/{$range->id}/deactivate");

        $response->assertSuccessful();
        $response->assertJsonPath('message', 'Range NSFP berhasil dinonaktifkan.');
        expect($range->fresh()->is_active)->toBeFalse();
    });
});

describe('NsfpRange utilization endpoint', function () {
    it('returns utilization summary', function () {
        NsfpRange::factory()->withRange(1, 1000)->forYear('26')->create([
            'next_number' => 501,
            'used_count' => 500,
        ]);
        NsfpRange::factory()->withRange(1001, 2000)->forYear('26')->create();

        $response = $this->getJson('/api/v1/nsfp-ranges-utilization');

        $response->assertSuccessful();
        $response->assertJsonPath('summary.total_ranges', 2);
        $response->assertJsonPath('summary.active_ranges', 2);
        $response->assertJsonPath('summary.total_capacity', 2000);
        $response->assertJsonPath('summary.total_used', 500);
        $response->assertJsonPath('summary.total_remaining', 1500);
    });

    it('filters utilization by year', function () {
        NsfpRange::factory()->forYear('25')->create();
        NsfpRange::factory()->forYear('26')->create();

        $response = $this->getJson('/api/v1/nsfp-ranges-utilization?year_code=26');

        $response->assertSuccessful();
        $response->assertJsonPath('summary.total_ranges', 1);
    });
});
