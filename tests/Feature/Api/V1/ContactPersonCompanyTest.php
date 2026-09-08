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

it('filters contacts by persons and companies kind', function () {
    Contact::factory()->create(['name' => 'PT Filter Co', 'is_company' => true]);
    Contact::factory()->create(['name' => 'Person Filter', 'is_company' => false]);

    $companies = $this->getJson('/api/v1/contacts?kind=companies');
    $companies->assertOk();
    expect(collect($companies->json('data'))->every(fn (array $row): bool => $row['is_company'] === true))->toBeTrue()
        ->and(collect($companies->json('data'))->pluck('name'))->toContain('PT Filter Co');

    $persons = $this->getJson('/api/v1/contacts?kind=persons');
    $persons->assertOk();
    expect(collect($persons->json('data'))->every(fn (array $row): bool => $row['is_company'] === false))->toBeTrue()
        ->and(collect($persons->json('data'))->pluck('name'))->toContain('Person Filter');
});

it('stores nested child contacts with invoice delivery and contact address roles', function () {
    $company = Contact::factory()->create([
        'name' => 'PT Nested',
        'is_company' => true,
    ]);

    $invoice = $this->postJson('/api/v1/contacts', [
        'code' => 'INV-1',
        'name' => 'Billing desk',
        'type' => Contact::TYPE_CUSTOMER,
        'is_company' => false,
        'parent_id' => $company->id,
        'address_role' => 'invoice',
    ]);
    $delivery = $this->postJson('/api/v1/contacts', [
        'code' => 'DEL-1',
        'name' => 'Warehouse gate',
        'type' => Contact::TYPE_CUSTOMER,
        'is_company' => false,
        'parent_id' => $company->id,
        'address_role' => 'delivery',
    ]);
    $other = $this->postJson('/api/v1/contacts', [
        'code' => 'CON-1',
        'name' => 'Front desk',
        'type' => Contact::TYPE_CUSTOMER,
        'is_company' => false,
        'parent_id' => $company->id,
        'address_role' => 'contact',
    ]);

    $invoice->assertCreated()->assertJsonPath('data.address_role', 'invoice');
    $delivery->assertCreated()->assertJsonPath('data.address_role', 'delivery');
    $other->assertCreated()->assertJsonPath('data.address_role', 'contact');

    $this->getJson("/api/v1/contacts/{$company->id}")
        ->assertOk()
        ->assertJsonPath('data.is_company', true)
        ->assertJsonCount(3, 'data.children');
});

it('rejects a person as the parent company', function () {
    $person = Contact::factory()->create(['is_company' => false]);

    $this->postJson('/api/v1/contacts', [
        'code' => 'BAD-1',
        'name' => 'Orphan',
        'type' => Contact::TYPE_CUSTOMER,
        'is_company' => false,
        'parent_id' => $person->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

it('rejects a contact as its own parent', function () {
    $contact = Contact::factory()->create();

    $this->putJson("/api/v1/contacts/{$contact->id}", [
        'parent_id' => $contact->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});
