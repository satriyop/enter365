<?php

use App\Models\Accounting\Contact;
use App\Models\Accounting\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Contact API', function () {
    
    it('can list all contacts', function () {
        Contact::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/contacts');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('can filter contacts by type customer', function () {
        Contact::factory()->customer()->count(3)->create();
        Contact::factory()->supplier()->count(2)->create();

        $response = $this->getJson('/api/v1/contacts?type=customer');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter contacts by type supplier', function () {
        Contact::factory()->customer()->count(3)->create();
        Contact::factory()->supplier()->count(2)->create();

        $response = $this->getJson('/api/v1/contacts?type=supplier');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can search contacts by name', function () {
        Contact::factory()->create(['name' => 'PT ABC Indonesia']);
        Contact::factory()->create(['name' => 'CV XYZ Makmur']);

        $response = $this->getJson('/api/v1/contacts?search=ABC');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'PT ABC Indonesia');
    });

    it('can create a customer contact', function () {
        $response = $this->postJson('/api/v1/contacts', [
            'code' => 'C-0001',
            'name' => 'PT Test Customer',
            'type' => Contact::TYPE_CUSTOMER,
            'email' => 'customer@test.com',
            'phone' => '021-12345678',
            'address' => 'Jl. Test No. 123',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'npwp' => '12.345.678.9-012.345',
            'credit_limit' => 50000000,
            'payment_term_days' => 30,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'C-0001')
            ->assertJsonPath('data.type', 'customer');
        
        $this->assertDatabaseHas('contacts', ['code' => 'C-0001']);
    });

    it('can create a supplier contact', function () {
        $response = $this->postJson('/api/v1/contacts', [
            'code' => 'S-0001',
            'name' => 'PT Test Supplier',
            'type' => Contact::TYPE_SUPPLIER,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'supplier');
    });

    it('can create a contact that is both customer and supplier', function () {
        $response = $this->postJson('/api/v1/contacts', [
            'code' => 'B-0001',
            'name' => 'PT Both Type',
            'type' => Contact::TYPE_BOTH,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'both');
    });

    it('validates required fields when creating contact', function () {
        $response = $this->postJson('/api/v1/contacts', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'type']);
    });

    it('validates contact type', function () {
        $response = $this->postJson('/api/v1/contacts', [
            'code' => 'C-0001',
            'name' => 'Test',
            'type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    });

    it('can show a single contact', function () {
        $contact = Contact::factory()->create();

        $response = $this->getJson("/api/v1/contacts/{$contact->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $contact->id);
    });

    it('can update a contact', function () {
        $contact = Contact::factory()->create();

        $response = $this->putJson("/api/v1/contacts/{$contact->id}", [
            'name' => 'Updated Company Name',
            'email' => 'updated@email.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Company Name')
            ->assertJsonPath('data.email', 'updated@email.com');
    });

    it('can delete a contact without transactions', function () {
        $contact = Contact::factory()->create();

        $response = $this->deleteJson("/api/v1/contacts/{$contact->id}");

        $response->assertOk();
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    });

    it('cannot delete contact with invoices', function () {
        $contact = Contact::factory()->customer()->create();
        Invoice::factory()->forContact($contact)->create();

        $response = $this->deleteJson("/api/v1/contacts/{$contact->id}");

        $response->assertUnprocessable();
    });

    it('can get contact balances', function () {
        $contact = Contact::factory()->both()->create();

        $response = $this->getJson("/api/v1/contacts/{$contact->id}/balances");

        $response->assertOk()
            ->assertJsonStructure(['contact_id', 'name', 'type', 'receivable_balance', 'payable_balance']);
    });
});
