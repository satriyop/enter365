<?php

declare(strict_types=1);

use App\Models\Contacts\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

it('stores and returns is_pkp next to npwp', function () {
    $response = $this->postJson('/api/v1/contacts', [
        'code' => 'C-PKP-1',
        'name' => 'PT PKP Customer',
        'type' => Contact::TYPE_CUSTOMER,
        'npwp' => '12.345.678.9-012.345',
        'is_pkp' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.is_pkp', true)
        ->assertJsonPath('data.npwp', '12.345.678.9-012.345');

    $this->assertDatabaseHas('contacts', [
        'code' => 'C-PKP-1',
        'is_pkp' => true,
    ]);
});

it('defaults is_pkp to false', function () {
    $response = $this->postJson('/api/v1/contacts', [
        'code' => 'C-NON-PKP',
        'name' => 'Toko Non PKP',
        'type' => Contact::TYPE_CUSTOMER,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.is_pkp', false);
});

it('can update is_pkp', function () {
    $contact = Contact::factory()->create(['is_pkp' => false]);

    $this->putJson("/api/v1/contacts/{$contact->id}", [
        'is_pkp' => true,
    ])->assertOk()
        ->assertJsonPath('data.is_pkp', true);
});
