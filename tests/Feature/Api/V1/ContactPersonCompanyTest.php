<?php

declare(strict_types=1);

use App\Models\Contacts\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

it('creates a company contact by default', function () {
    $response = $this->postJson('/api/v1/contacts', [
        'code' => 'CO-1',
        'name' => 'PT Kopi',
        'type' => Contact::TYPE_CUSTOMER,
        'country' => 'ID',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.is_company', true)
        ->assertJsonPath('data.parent_id', null)
        ->assertJsonPath('data.country', 'ID');
});

it('creates a person contact under a company', function () {
    $company = Contact::factory()->create([
        'name' => 'PT Kopi',
        'is_company' => true,
    ]);

    $response = $this->postJson('/api/v1/contacts', [
        'code' => 'PE-1',
        'name' => 'Rina Akuntan',
        'type' => Contact::TYPE_CUSTOMER,
        'is_company' => false,
        'parent_id' => $company->id,
        'job_position' => 'Accountant',
        'address_line_2' => 'Lt. 2',
        'country' => 'ID',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.is_company', false)
        ->assertJsonPath('data.parent_id', $company->id)
        ->assertJsonPath('data.job_position', 'Accountant')
        ->assertJsonPath('data.address_line_2', 'Lt. 2');

    expect($company->children()->count())->toBe(1);
});

it('rejects a contact as its own parent', function () {
    $contact = Contact::factory()->create();

    $this->putJson("/api/v1/contacts/{$contact->id}", [
        'parent_id' => $contact->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});
