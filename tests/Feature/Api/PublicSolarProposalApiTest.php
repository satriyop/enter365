<?php

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomVariantGroup;
use App\Models\Solar\SolarProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('solar-proposal', 'public-api');

// Seed required reference data
beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\IndonesiaSolarDataSeeder']);
});

describe('Public Solar Proposal Portal', function () {
    beforeEach(function () {
        $this->contact = Contact::factory()->create();
        $user = User::factory()->create();

        // Create a variant group with BOM
        $this->variantGroup = BomVariantGroup::factory()->create();
        $this->bom = Bom::factory()->create([
            'variant_group_id' => $this->variantGroup->id,
            'name' => '10 kWp Solar System',
        ]);

        // Create a sent proposal (with public token already generated)
        $this->proposal = SolarProposal::factory()
            ->sent()
            ->withVariantGroup($this->variantGroup)
            ->calculated()
            ->create([
                'contact_id' => $this->contact->id,
                'province' => 'DKI Jakarta',
                'city' => 'Jakarta Selatan',
                'selected_bom_id' => $this->bom->id,
                'system_capacity_kwp' => 10,
                'annual_production_kwh' => 12000,
                'electricity_rate' => 1500,
                'valid_until' => now()->addDays(30),
                'created_by' => $user->id,
            ]);
    });

    it('can view proposal via public token', function () {
        $response = $this->getJson("/api/v1/public/solar-proposals/{$this->proposal->public_token}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'proposal_number',
                    'status',
                    'status_label',
                    'contact' => ['name'],
                    'site_name',
                    'site_address',
                    'province',
                    'city',
                    'system_capacity_kwp',
                    'annual_production_kwh',
                    'valid_until',
                    'can_accept',
                    'can_reject',
                ],
            ]);

        expect($response->json('data.id'))->toBe($this->proposal->id);
        expect($response->json('data.can_accept'))->toBeTrue();
        expect($response->json('data.can_reject'))->toBeTrue();
    });

    it('returns 404 for invalid token', function () {
        $response = $this->getJson('/api/v1/public/solar-proposals/invalid-token-12345');

        $response->assertNotFound()
            ->assertJson(['message' => 'Proposal tidak ditemukan.']);
    });

    it('returns 410 for expired token', function () {
        // Set the token as expired
        $this->proposal->update([
            'public_token_expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/v1/public/solar-proposals/{$this->proposal->public_token}");

        $response->assertStatus(410)
            ->assertJson(['message' => 'Link proposal sudah kedaluwarsa.']);
    });

    it('can accept proposal via public token', function () {
        $response = $this->postJson("/api/v1/public/solar-proposals/{$this->proposal->public_token}/accept");

        $response->assertOk()
            ->assertJson([
                'message' => 'Proposal berhasil diterima.',
                'data' => [
                    'id' => $this->proposal->id,
                ],
            ])
            ->assertJsonPath('data.status.value', 'accepted');

        $this->proposal->refresh();
        expect($this->proposal->status)->toBe(DocumentStatus::Accepted);
        expect($this->proposal->accepted_at)->not->toBeNull();
    });

    it('cannot accept proposal twice', function () {
        // First accept
        $this->postJson("/api/v1/public/solar-proposals/{$this->proposal->public_token}/accept")
            ->assertOk();

        // Second accept
        $response = $this->postJson("/api/v1/public/solar-proposals/{$this->proposal->public_token}/accept");

        $response->assertUnprocessable()
            ->assertJson(['message' => 'Proposal tidak dapat diterima.']);
    });

    it('can reject proposal via public token', function () {
        $response = $this->postJson("/api/v1/public/solar-proposals/{$this->proposal->public_token}/reject", [
            'reason' => 'Harga terlalu mahal',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Proposal berhasil ditolak.',
                'data' => [
                    'id' => $this->proposal->id,
                ],
            ])
            ->assertJsonPath('data.status.value', 'rejected');

        $this->proposal->refresh();
        expect($this->proposal->status)->toBe(DocumentStatus::Rejected);
        expect($this->proposal->rejection_reason)->toBe('Harga terlalu mahal');
        expect($this->proposal->rejected_at)->not->toBeNull();
    });

    it('can reject proposal without reason', function () {
        $response = $this->postJson("/api/v1/public/solar-proposals/{$this->proposal->public_token}/reject");

        $response->assertOk();

        $this->proposal->refresh();
        expect($this->proposal->status)->toBe(DocumentStatus::Rejected);
        expect($this->proposal->rejection_reason)->toBeNull();
    });

    it('cannot reject proposal twice', function () {
        // First reject
        $this->postJson("/api/v1/public/solar-proposals/{$this->proposal->public_token}/reject")
            ->assertOk();

        // Second reject
        $response = $this->postJson("/api/v1/public/solar-proposals/{$this->proposal->public_token}/reject");

        $response->assertUnprocessable()
            ->assertJson(['message' => 'Proposal tidak dapat ditolak.']);
    });

    it('cannot accept or reject expired proposal', function () {
        $this->proposal->update([
            'valid_until' => now()->subDay(),
        ]);

        $acceptResponse = $this->postJson("/api/v1/public/solar-proposals/{$this->proposal->public_token}/accept");
        $acceptResponse->assertUnprocessable();

        $rejectResponse = $this->postJson("/api/v1/public/solar-proposals/{$this->proposal->public_token}/reject");
        $rejectResponse->assertUnprocessable();
    });
});
