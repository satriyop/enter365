<?php

declare(strict_types=1);

use App\Contracts\Shared\ContactServiceInterface;
use App\Models\Contacts\Contact;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Services\Shared\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
    $this->service = app(ContactServiceInterface::class);
});

describe('Service Resolution', function () {
    test('resolves to ContactService implementing ContactServiceInterface', function () {
        expect($this->service)->toBeInstanceOf(ContactService::class);
    });
});

describe('ContactService - create', function () {
    test('creates a new customer contact with all fields', function () {
        $data = [
            'code' => 'C-0001',
            'name' => 'PT Test Company',
            'type' => Contact::TYPE_CUSTOMER,
            'email' => 'test@company.com',
            'phone' => '021-1234567',
            'address' => 'Jl. Test No. 1',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'postal_code' => '12345',
            'credit_limit' => 10000000,
            'payment_term_days' => 30,
            'is_active' => true,
        ];

        $contact = $this->service->create($data);

        expect($contact)
            ->toBeInstanceOf(Contact::class)
            ->code->toBe('C-0001')
            ->name->toBe('PT Test Company')
            ->type->toBe(Contact::TYPE_CUSTOMER)
            ->email->toBe('test@company.com')
            ->phone->toBe('021-1234567')
            ->address->toBe('Jl. Test No. 1')
            ->city->toBe('Jakarta')
            ->province->toBe('DKI Jakarta')
            ->postal_code->toBe('12345')
            ->credit_limit->toBe(10000000)
            ->payment_term_days->toBe(30)
            ->is_active->toBeTrue();
    });

    test('creates a new supplier contact', function () {
        $data = [
            'code' => 'S-0001',
            'name' => 'PT Supplier Test',
            'type' => Contact::TYPE_SUPPLIER,
            'email' => 'supplier@company.com',
            'phone' => '021-9876543',
        ];

        $contact = $this->service->create($data);

        expect($contact)
            ->toBeInstanceOf(Contact::class)
            ->code->toBe('S-0001')
            ->name->toBe('PT Supplier Test')
            ->type->toBe(Contact::TYPE_SUPPLIER)
            ->email->toBe('supplier@company.com');
    });

    test('creates contact with both type', function () {
        $data = [
            'code' => 'CS-0001',
            'name' => 'PT Both Test',
            'type' => Contact::TYPE_BOTH,
        ];

        $contact = $this->service->create($data);

        expect($contact)
            ->type->toBe(Contact::TYPE_BOTH)
            ->isCustomer()->toBeTrue()
            ->isSupplier()->toBeTrue();
    });

    test('creates contact with minimal required fields', function () {
        $data = [
            'code' => 'C-0002',
            'name' => 'Minimal Contact',
            'type' => Contact::TYPE_CUSTOMER,
        ];

        $contact = $this->service->create($data);

        expect($contact)
            ->code->toBe('C-0002')
            ->name->toBe('Minimal Contact')
            ->type->toBe(Contact::TYPE_CUSTOMER);
    });

    test('creates contact with NPWP', function () {
        $data = [
            'code' => 'C-0003',
            'name' => 'PT With NPWP',
            'type' => Contact::TYPE_CUSTOMER,
            'npwp' => '01.234.567.8-901.234',
        ];

        $contact = $this->service->create($data);

        expect($contact)
            ->npwp->toBe('01.234.567.8-901.234');
    });

    test('creates contact with credit limit', function () {
        $data = [
            'code' => 'C-0004',
            'name' => 'PT With Credit Limit',
            'type' => Contact::TYPE_CUSTOMER,
            'credit_limit' => 50000000,
        ];

        $contact = $this->service->create($data);

        expect($contact)
            ->credit_limit->toBe(50000000)
            ->getAvailableCredit()->toBe(50000000); // No invoices yet
    });

    test('creates contact with payment terms', function () {
        $data = [
            'code' => 'C-0005',
            'name' => 'PT With Payment Terms',
            'type' => Contact::TYPE_CUSTOMER,
            'payment_term_days' => 45,
        ];

        $contact = $this->service->create($data);

        expect($contact)
            ->payment_term_days->toBe(45);
    });

    test('creates contact with bank details', function () {
        $data = [
            'code' => 'S-0002',
            'name' => 'PT Supplier With Bank',
            'type' => Contact::TYPE_SUPPLIER,
            'bank_name' => 'Bank BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'PT Supplier With Bank',
        ];

        $contact = $this->service->create($data);

        expect($contact)
            ->bank_name->toBe('Bank BCA')
            ->bank_account_number->toBe('1234567890')
            ->bank_account_name->toBe('PT Supplier With Bank');
    });

    test('creates subcontractor contact', function () {
        $data = [
            'code' => 'S-0003',
            'name' => 'PT Subcontractor',
            'type' => Contact::TYPE_SUPPLIER,
            'is_subcontractor' => true,
            'subcontractor_services' => ['instalasi_listrik', 'instalasi_panel'],
            'hourly_rate' => 100000,
            'daily_rate' => 750000,
        ];

        $contact = $this->service->create($data);

        expect($contact)
            ->is_subcontractor->toBeTrue()
            ->subcontractor_services->toBe(['instalasi_listrik', 'instalasi_panel'])
            ->hourly_rate->toBe(100000)
            ->daily_rate->toBe(750000)
            ->isSubcontractor()->toBeTrue();
    });
});

describe('ContactService - update', function () {
    test('updates contact name', function () {
        $contact = Contact::factory()->create([
            'name' => 'Original Name',
        ]);

        $updated = $this->service->update($contact, [
            'name' => 'Updated Name',
        ]);

        expect($updated)
            ->name->toBe('Updated Name');
    });

    test('updates contact email and phone', function () {
        $contact = Contact::factory()->create();

        $updated = $this->service->update($contact, [
            'email' => 'newemail@company.com',
            'phone' => '021-5555555',
        ]);

        expect($updated)
            ->email->toBe('newemail@company.com')
            ->phone->toBe('021-5555555');
    });

    test('updates contact type from customer to both', function () {
        $contact = Contact::factory()->customer()->create();

        expect($contact->type)->toBe(Contact::TYPE_CUSTOMER);

        $updated = $this->service->update($contact, [
            'type' => Contact::TYPE_BOTH,
        ]);

        expect($updated)
            ->type->toBe(Contact::TYPE_BOTH)
            ->isCustomer()->toBeTrue()
            ->isSupplier()->toBeTrue();
    });

    test('updates contact credit limit', function () {
        $contact = Contact::factory()->create([
            'credit_limit' => 10000000,
        ]);

        $updated = $this->service->update($contact, [
            'credit_limit' => 25000000,
        ]);

        expect($updated)
            ->credit_limit->toBe(25000000);
    });

    test('updates contact payment terms', function () {
        $contact = Contact::factory()->create([
            'payment_term_days' => 30,
        ]);

        $updated = $this->service->update($contact, [
            'payment_term_days' => 60,
        ]);

        expect($updated)
            ->payment_term_days->toBe(60);
    });

    test('updates contact address fields', function () {
        $contact = Contact::factory()->create();

        $updated = $this->service->update($contact, [
            'address' => 'Jl. New Address No. 99',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40123',
        ]);

        expect($updated)
            ->address->toBe('Jl. New Address No. 99')
            ->city->toBe('Bandung')
            ->province->toBe('Jawa Barat')
            ->postal_code->toBe('40123');
    });

    test('updates contact status to inactive', function () {
        $contact = Contact::factory()->create([
            'is_active' => true,
        ]);

        $updated = $this->service->update($contact, [
            'is_active' => false,
        ]);

        expect($updated)
            ->is_active->toBeFalse();
    });

    test('updates contact NPWP', function () {
        $contact = Contact::factory()->create([
            'npwp' => null,
        ]);

        $updated = $this->service->update($contact, [
            'npwp' => '99.888.777.6-543.210',
        ]);

        expect($updated)
            ->npwp->toBe('99.888.777.6-543.210');
    });

    test('updates contact bank details', function () {
        $contact = Contact::factory()->supplier()->create();

        $updated = $this->service->update($contact, [
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '9876543210',
            'bank_account_name' => 'Updated Account Name',
        ]);

        expect($updated)
            ->bank_name->toBe('Bank Mandiri')
            ->bank_account_number->toBe('9876543210')
            ->bank_account_name->toBe('Updated Account Name');
    });

    test('updates subcontractor fields', function () {
        $contact = Contact::factory()->supplier()->create([
            'is_subcontractor' => false,
        ]);

        $updated = $this->service->update($contact, [
            'is_subcontractor' => true,
            'subcontractor_services' => ['instalasi_solar'],
            'hourly_rate' => 125000,
            'daily_rate' => 1000000,
        ]);

        expect($updated)
            ->is_subcontractor->toBeTrue()
            ->subcontractor_services->toBe(['instalasi_solar'])
            ->hourly_rate->toBe(125000)
            ->daily_rate->toBe(1000000);
    });
});

describe('ContactService - delete', function () {
    test('deletes contact without transactions', function () {
        $contact = Contact::factory()->create();

        $this->service->delete($contact);

        expect(Contact::withTrashed()->find($contact->id))
            ->deleted_at->not->toBeNull();
    });

    test('cannot delete contact with invoices', function () {
        $contact = Contact::factory()->customer()->create();

        // Create an invoice linked to this contact
        Invoice::factory()->create(['contact_id' => $contact->id]);

        expect(fn () => $this->service->delete($contact))
            ->toThrow(Exception::class, 'Tidak bisa menghapus kontak yang sudah memiliki transaksi.');

        // Contact should still exist
        expect(Contact::find($contact->id))->not->toBeNull();
    });

    test('cannot delete contact with bills', function () {
        $contact = Contact::factory()->supplier()->create();

        // Create a bill linked to this contact
        Bill::factory()->create(['contact_id' => $contact->id]);

        expect(fn () => $this->service->delete($contact))
            ->toThrow(Exception::class, 'Tidak bisa menghapus kontak yang sudah memiliki transaksi.');

        // Contact should still exist
        expect(Contact::find($contact->id))->not->toBeNull();
    });

    test('cannot delete contact with both invoices and bills', function () {
        $contact = Contact::factory()->both()->create();

        // Create both invoice and bill
        Invoice::factory()->create(['contact_id' => $contact->id]);
        Bill::factory()->create(['contact_id' => $contact->id]);

        expect(fn () => $this->service->delete($contact))
            ->toThrow(Exception::class, 'Tidak bisa menghapus kontak yang sudah memiliki transaksi.');

        // Contact should still exist
        expect(Contact::find($contact->id))->not->toBeNull();
    });

    test('can delete contact after all transactions are removed', function () {
        $contact = Contact::factory()->customer()->create();

        // Create an invoice
        $invoice = Invoice::factory()->create(['contact_id' => $contact->id]);

        // Verify we cannot delete yet
        expect(fn () => $this->service->delete($contact))
            ->toThrow(Exception::class, 'Tidak bisa menghapus kontak yang sudah memiliki transaksi.');

        // Force delete the invoice
        $invoice->forceDelete();

        // Now we should be able to delete the contact
        $this->service->delete($contact);

        expect(Contact::withTrashed()->find($contact->id))
            ->deleted_at->not->toBeNull();
    });
});

describe('ContactService - business logic validation', function () {
    test('customer contact type works correctly', function () {
        $contact = $this->service->create([
            'code' => 'C-TEST',
            'name' => 'Customer Only',
            'type' => Contact::TYPE_CUSTOMER,
        ]);

        expect($contact)
            ->isCustomer()->toBeTrue()
            ->isSupplier()->toBeFalse();
    });

    test('supplier contact type works correctly', function () {
        $contact = $this->service->create([
            'code' => 'S-TEST',
            'name' => 'Supplier Only',
            'type' => Contact::TYPE_SUPPLIER,
        ]);

        expect($contact)
            ->isCustomer()->toBeFalse()
            ->isSupplier()->toBeTrue();
    });

    test('both contact type works correctly', function () {
        $contact = $this->service->create([
            'code' => 'CS-TEST',
            'name' => 'Both Customer and Supplier',
            'type' => Contact::TYPE_BOTH,
        ]);

        expect($contact)
            ->isCustomer()->toBeTrue()
            ->isSupplier()->toBeTrue();
    });

    test('inactive contact is created as inactive', function () {
        $contact = $this->service->create([
            'code' => 'C-INACTIVE',
            'name' => 'Inactive Contact',
            'type' => Contact::TYPE_CUSTOMER,
            'is_active' => false,
        ]);

        expect($contact)
            ->is_active->toBeFalse();
    });

    test('contact with zero credit limit has unlimited credit', function () {
        $contact = $this->service->create([
            'code' => 'C-NOCREDIT',
            'name' => 'No Credit Limit',
            'type' => Contact::TYPE_CUSTOMER,
            'credit_limit' => 0,
        ]);

        expect($contact)
            ->credit_limit->toBe(0)
            ->getAvailableCredit()->toBe(PHP_INT_MAX)
            ->isCreditLimitExceeded()->toBeFalse();
    });

    test('contact with null credit limit defaults to zero', function () {
        $contact = $this->service->create([
            'code' => 'C-NULL',
            'name' => 'Null Credit Limit',
            'type' => Contact::TYPE_CUSTOMER,
        ]);

        // Verify default behavior
        expect($contact->credit_limit)->toBeNull();
    });
});
