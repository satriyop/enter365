<?php

use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Models\Purchasing\Bill;
use App\Models\Sales\DownPayment;
use App\Models\Sales\DownPaymentApplication;
use App\Models\Sales\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);

    // Seed chart of accounts
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);

    // Create additional accounts with unique codes
    $this->bankAccount = Account::factory()->create([
        'code' => '1111',
    ]);
    $this->downPaymentAccount = Account::factory()->downPaymentAsset()->create([
        'code' => '1510',
    ]);
    $this->discountGivenAccount = Account::factory()->revenue()->create([
        'code' => '4100',
    ]);
    $this->discountReceivedAccount = Account::factory()->expense()->create([
        'code' => '5300',
    ]);
});

describe('Down Payment CRUD', function () {

    it('can list all down payments', function () {
        DownPayment::factory()->receivable()->count(3)->create();
        DownPayment::factory()->payable()->count(2)->create();

        $response = $this->getJson('/api/v1/down-payments');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('can filter down payments by type', function () {
        DownPayment::factory()->receivable()->count(3)->create();
        DownPayment::factory()->payable()->count(2)->create();

        $response = $this->getJson('/api/v1/down-payments?type=receivable');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter down payments by status', function () {
        DownPayment::factory()->count(3)->create(['status' => DownPayment::STATUS_ACTIVE]);
        DownPayment::factory()->fullyApplied()->count(2)->create();

        $response = $this->getJson('/api/v1/down-payments?status=active');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter down payments by contact', function () {
        $contact = Contact::factory()->customer()->create();
        DownPayment::factory()->receivable()->forContact($contact)->count(2)->create();
        DownPayment::factory()->receivable()->count(3)->create();

        $response = $this->getJson("/api/v1/down-payments?contact_id={$contact->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter available down payments only', function () {
        DownPayment::factory()->count(3)->create(['status' => DownPayment::STATUS_ACTIVE]);
        DownPayment::factory()->fullyApplied()->count(2)->create();

        $response = $this->getJson('/api/v1/down-payments?available_only=true');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can search down payments', function () {
        $dp = DownPayment::factory()->receivable()->create([
            'reference' => 'QUO-2025-001',
        ]);
        DownPayment::factory()->receivable()->count(3)->create();

        $response = $this->getJson('/api/v1/down-payments?search=QUO-2025-001');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'QUO-2025-001');
    });

    it('can create a receivable down payment', function () {
        $contact = Contact::factory()->customer()->create();
        $bankAccount = Account::where('code', '1111')->first();

        $response = $this->postJson('/api/v1/down-payments', [
            'type' => 'receivable',
            'contact_id' => $contact->id,
            'dp_date' => '2025-12-26',
            'amount' => 10000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $bankAccount->id,
            'reference' => 'QUO-2025-001',
            'description' => 'Down payment for solar panel project',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'receivable')
            ->assertJsonPath('data.amount', 10000000)
            ->assertJsonPath('data.applied_amount', 0)
            ->assertJsonPath('data.remaining_amount', 10000000)
            ->assertJsonPath('data.status', 'active');

        expect($response->json('data.dp_number'))->toStartWith('DPR-');
    });

    it('can create a payable down payment', function () {
        $contact = Contact::factory()->vendor()->create();
        $bankAccount = Account::where('code', '1111')->first();

        $response = $this->postJson('/api/v1/down-payments', [
            'type' => 'payable',
            'contact_id' => $contact->id,
            'dp_date' => '2025-12-26',
            'amount' => 5000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $bankAccount->id,
            'reference' => 'PO-2025-001',
            'description' => 'Down payment for MCB components',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'payable')
            ->assertJsonPath('data.amount', 5000000)
            ->assertJsonPath('data.status', 'active');

        expect($response->json('data.dp_number'))->toStartWith('DPP-');
    });

    it('validates required fields when creating down payment', function () {
        $response = $this->postJson('/api/v1/down-payments', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'contact_id', 'dp_date', 'amount', 'payment_method', 'cash_account_id']);
    });

    it('can show a single down payment', function () {
        $downPayment = DownPayment::factory()->receivable()->create();

        $response = $this->getJson("/api/v1/down-payments/{$downPayment->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $downPayment->id)
            ->assertJsonPath('data.dp_number', $downPayment->dp_number);
    });

    it('can update a down payment without applications', function () {
        $downPayment = DownPayment::factory()->receivable()->create([
            'amount' => 10000000,
        ]);

        $response = $this->putJson("/api/v1/down-payments/{$downPayment->id}", [
            'amount' => 15000000,
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.amount', 15000000)
            ->assertJsonPath('data.description', 'Updated description');
    });

    it('cannot update a down payment with applications', function () {
        $downPayment = DownPayment::factory()->receivable()->partiallyApplied(5000000)->create();
        DownPaymentApplication::factory()->forDownPayment($downPayment)->create();

        $response = $this->putJson("/api/v1/down-payments/{$downPayment->id}", [
            'amount' => 15000000,
        ]);

        $response->assertUnprocessable();
    });

    it('can delete a down payment without applications', function () {
        $downPayment = DownPayment::factory()->receivable()->create();

        $response = $this->deleteJson("/api/v1/down-payments/{$downPayment->id}");

        $response->assertOk();
        $this->assertSoftDeleted('down_payments', ['id' => $downPayment->id]);
    });

    it('cannot delete a down payment with applications', function () {
        $downPayment = DownPayment::factory()->receivable()->partiallyApplied(5000000)->create();
        DownPaymentApplication::factory()->forDownPayment($downPayment)->create();

        $response = $this->deleteJson("/api/v1/down-payments/{$downPayment->id}");

        $response->assertUnprocessable();
    });
});

describe('Down Payment Apply to Invoice', function () {

    it('can apply receivable down payment to invoice', function () {
        $contact = Contact::factory()->customer()->create();
        $bankAccount = Account::where('code', '1111')->first();
        $receivableAccount = Account::where('code', '1-1100')->first();

        $downPayment = DownPayment::factory()->receivable()->forContact($contact)->create([
            'amount' => 10000000,
            'cash_account_id' => $bankAccount->id,
        ]);

        $invoice = Invoice::factory()->create([
            'contact_id' => $contact->id,
            'total_amount' => 15000000,
            'paid_amount' => 0,
            'status' => DocumentStatus::Sent,
            'receivable_account_id' => $receivableAccount->id,
        ]);

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/apply-to-invoice/{$invoice->id}", [
            'amount' => 5000000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('application.amount', 5000000);

        $downPayment->refresh();
        $invoice->refresh();

        expect($downPayment->applied_amount)->toBe(5000000);
        expect($downPayment->getRemainingAmount())->toBe(5000000);
        expect($invoice->paid_amount)->toBe(5000000);
    });

    it('cannot apply more than remaining balance', function () {
        $contact = Contact::factory()->customer()->create();
        $bankAccount = Account::where('code', '1111')->first();

        $downPayment = DownPayment::factory()->receivable()->forContact($contact)->create([
            'amount' => 5000000,
            'cash_account_id' => $bankAccount->id,
        ]);

        $invoice = Invoice::factory()->create([
            'contact_id' => $contact->id,
            'total_amount' => 15000000,
            'paid_amount' => 0,
        ]);

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/apply-to-invoice/{$invoice->id}", [
            'amount' => 10000000,
        ]);

        $response->assertUnprocessable();
    });

    it('cannot apply more than invoice outstanding', function () {
        $contact = Contact::factory()->customer()->create();
        $bankAccount = Account::where('code', '1111')->first();

        $downPayment = DownPayment::factory()->receivable()->forContact($contact)->create([
            'amount' => 20000000,
            'cash_account_id' => $bankAccount->id,
        ]);

        $invoice = Invoice::factory()->create([
            'contact_id' => $contact->id,
            'total_amount' => 10000000,
            'paid_amount' => 5000000,
        ]);

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/apply-to-invoice/{$invoice->id}", [
            'amount' => 10000000, // Only 5M outstanding
        ]);

        $response->assertUnprocessable();
    });

    it('cannot apply to invoice with different contact', function () {
        $contact1 = Contact::factory()->customer()->create();
        $contact2 = Contact::factory()->customer()->create();
        $bankAccount = Account::where('code', '1111')->first();

        $downPayment = DownPayment::factory()->receivable()->forContact($contact1)->create([
            'cash_account_id' => $bankAccount->id,
        ]);

        $invoice = Invoice::factory()->create([
            'contact_id' => $contact2->id,
        ]);

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/apply-to-invoice/{$invoice->id}", [
            'amount' => 1000000,
        ]);

        $response->assertUnprocessable();
    });

    it('cannot apply payable down payment to invoice', function () {
        $contact = Contact::factory()->vendor()->create();
        $bankAccount = Account::where('code', '1111')->first();

        $downPayment = DownPayment::factory()->payable()->forContact($contact)->create([
            'cash_account_id' => $bankAccount->id,
        ]);

        $invoice = Invoice::factory()->create([
            'contact_id' => $contact->id,
        ]);

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/apply-to-invoice/{$invoice->id}", [
            'amount' => 1000000,
        ]);

        $response->assertUnprocessable();
    });

    it('marks invoice as paid when fully covered', function () {
        $contact = Contact::factory()->customer()->create();
        $bankAccount = Account::where('code', '1111')->first();
        $receivableAccount = Account::where('code', '1-1100')->first();

        $downPayment = DownPayment::factory()->receivable()->forContact($contact)->create([
            'amount' => 10000000,
            'cash_account_id' => $bankAccount->id,
        ]);

        $invoice = Invoice::factory()->create([
            'contact_id' => $contact->id,
            'total_amount' => 10000000,
            'paid_amount' => 0,
            'status' => DocumentStatus::Sent,
            'receivable_account_id' => $receivableAccount->id,
        ]);

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/apply-to-invoice/{$invoice->id}", [
            'amount' => 10000000,
        ]);

        $response->assertCreated();

        $invoice->refresh();
        expect($invoice->status)->toBe(DocumentStatus::Paid);
    });
});

describe('Down Payment Apply to Bill', function () {

    it('can apply payable down payment to bill', function () {
        $contact = Contact::factory()->vendor()->create();
        $bankAccount = Account::where('code', '1111')->first();
        $payableAccount = Account::where('code', '2-1100')->first();

        $downPayment = DownPayment::factory()->payable()->forContact($contact)->create([
            'amount' => 10000000,
            'cash_account_id' => $bankAccount->id,
        ]);

        $bill = Bill::factory()->create([
            'contact_id' => $contact->id,
            'total_amount' => 15000000,
            'paid_amount' => 0,
            'status' => DocumentStatus::Received,
            'payable_account_id' => $payableAccount->id,
        ]);

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/apply-to-bill/{$bill->id}", [
            'amount' => 5000000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('application.amount', 5000000);

        $downPayment->refresh();
        $bill->refresh();

        expect($downPayment->applied_amount)->toBe(5000000);
        expect($bill->paid_amount)->toBe(5000000);
    });

    it('cannot apply receivable down payment to bill', function () {
        $contact = Contact::factory()->customer()->create();
        $bankAccount = Account::where('code', '1111')->first();

        $downPayment = DownPayment::factory()->receivable()->forContact($contact)->create([
            'cash_account_id' => $bankAccount->id,
        ]);

        $bill = Bill::factory()->create([
            'contact_id' => $contact->id,
        ]);

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/apply-to-bill/{$bill->id}", [
            'amount' => 1000000,
        ]);

        $response->assertUnprocessable();
    });
});

describe('Down Payment Unapply', function () {

    it('can unapply (reverse) a down payment application', function () {
        $contact = Contact::factory()->customer()->create();
        $bankAccount = Account::where('code', '1111')->first();
        $receivableAccount = Account::where('code', '1-1100')->first();

        $downPayment = DownPayment::factory()->receivable()->forContact($contact)->create([
            'amount' => 10000000,
            'applied_amount' => 5000000,
            'cash_account_id' => $bankAccount->id,
        ]);

        $invoice = Invoice::factory()->create([
            'contact_id' => $contact->id,
            'total_amount' => 10000000,
            'paid_amount' => 5000000,
            'receivable_account_id' => $receivableAccount->id,
        ]);

        $application = DownPaymentApplication::factory()
            ->forDownPayment($downPayment)
            ->toInvoice($invoice)
            ->withAmount(5000000)
            ->create();

        $response = $this->deleteJson("/api/v1/down-payments/{$downPayment->id}/applications/{$application->id}");

        $response->assertOk();

        $downPayment->refresh();
        $invoice->refresh();

        expect($downPayment->applied_amount)->toBe(0);
        expect($downPayment->status)->toBe(DownPayment::STATUS_ACTIVE);
        expect($invoice->paid_amount)->toBe(0);
    });

    it('cannot unapply application belonging to different down payment', function () {
        $downPayment1 = DownPayment::factory()->receivable()->create();
        $downPayment2 = DownPayment::factory()->receivable()->create();

        $application = DownPaymentApplication::factory()
            ->forDownPayment($downPayment2)
            ->create();

        $response = $this->deleteJson("/api/v1/down-payments/{$downPayment1->id}/applications/{$application->id}");

        $response->assertUnprocessable();
    });
});

describe('Down Payment Refund', function () {

    it('can refund remaining down payment balance', function () {
        $contact = Contact::factory()->customer()->create();
        $bankAccount = Account::where('code', '1111')->first();

        $downPayment = DownPayment::factory()->receivable()->forContact($contact)->create([
            'amount' => 10000000,
            'applied_amount' => 3000000,
            'cash_account_id' => $bankAccount->id,
        ]);

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/refund", [
            'amount' => 7000000,
        ]);

        $response->assertOk()
            ->assertJsonPath('refund_payment.amount', 7000000);

        $downPayment->refresh();
        expect($downPayment->status)->toBe(DownPayment::STATUS_REFUNDED);
        expect($downPayment->refunded_at)->not->toBeNull();
    });

    it('can refund partial amount', function () {
        $contact = Contact::factory()->customer()->create();
        $bankAccount = Account::where('code', '1111')->first();

        $downPayment = DownPayment::factory()->receivable()->forContact($contact)->create([
            'amount' => 10000000,
            'applied_amount' => 0,
            'cash_account_id' => $bankAccount->id,
        ]);

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/refund", [
            'amount' => 5000000,
        ]);

        $response->assertOk()
            ->assertJsonPath('refund_payment.amount', 5000000);
    });

    it('cannot refund more than remaining balance', function () {
        $contact = Contact::factory()->customer()->create();
        $bankAccount = Account::where('code', '1111')->first();

        $downPayment = DownPayment::factory()->receivable()->forContact($contact)->create([
            'amount' => 10000000,
            'applied_amount' => 8000000,
            'cash_account_id' => $bankAccount->id,
        ]);

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/refund", [
            'amount' => 5000000, // Only 2M remaining
        ]);

        $response->assertUnprocessable();
    });

    it('cannot refund fully applied down payment', function () {
        $downPayment = DownPayment::factory()->receivable()->fullyApplied()->create();

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/refund");

        $response->assertUnprocessable();
    });
});

describe('Down Payment Cancel', function () {

    it('can cancel an active down payment', function () {
        $downPayment = DownPayment::factory()->receivable()->create();

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/cancel", [
            'reason' => 'Customer cancelled project',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', DocumentStatus::Cancelled->value);

        $downPayment->refresh();
        expect($downPayment->notes)->toContain('Cancelled: Customer cancelled project');
    });

    it('cannot cancel down payment with applications', function () {
        $downPayment = DownPayment::factory()->receivable()->partiallyApplied(5000000)->create();
        DownPaymentApplication::factory()->forDownPayment($downPayment)->create();

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/cancel");

        $response->assertUnprocessable();
    });

    it('cannot cancel non-active down payment', function () {
        $downPayment = DownPayment::factory()->receivable()->refunded()->create();

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/cancel");

        $response->assertUnprocessable();
    });
});

describe('Down Payment Available', function () {

    it('returns available down payments for a contact', function () {
        $contact = Contact::factory()->customer()->create();
        $bankAccount = Account::where('code', '1111')->first();

        DownPayment::factory()->receivable()->forContact($contact)->count(2)->create([
            'cash_account_id' => $bankAccount->id,
        ]);
        DownPayment::factory()->receivable()->forContact($contact)->fullyApplied()->create([
            'cash_account_id' => $bankAccount->id,
        ]);

        // Different contact
        DownPayment::factory()->receivable()->create([
            'cash_account_id' => $bankAccount->id,
        ]);

        $response = $this->getJson("/api/v1/down-payments-available?contact_id={$contact->id}&type=receivable");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('requires contact_id and type', function () {
        $response = $this->getJson('/api/v1/down-payments-available');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id', 'type']);
    });
});

describe('Down Payment Statistics', function () {

    it('returns down payment statistics', function () {
        $bankAccount = Account::where('code', '1111')->first();

        DownPayment::factory()->receivable()->count(3)->create([
            'amount' => 10000000,
            'cash_account_id' => $bankAccount->id,
        ]);
        DownPayment::factory()->payable()->count(2)->create([
            'amount' => 5000000,
            'cash_account_id' => $bankAccount->id,
        ]);
        DownPayment::factory()->receivable()->fullyApplied()->create([
            'amount' => 10000000,
            'applied_amount' => 10000000,
            'cash_account_id' => $bankAccount->id,
        ]);

        $response = $this->getJson('/api/v1/down-payments-statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'total_count',
                'total_amount',
                'total_applied',
                'total_remaining',
                'by_status',
                'by_type',
            ]);

        expect($response->json('total_count'))->toBe(6);
    });

    it('can filter statistics by type', function () {
        $bankAccount = Account::where('code', '1111')->first();

        DownPayment::factory()->receivable()->count(3)->create([
            'amount' => 10000000,
            'cash_account_id' => $bankAccount->id,
        ]);
        DownPayment::factory()->payable()->count(2)->create([
            'amount' => 5000000,
            'cash_account_id' => $bankAccount->id,
        ]);

        $response = $this->getJson('/api/v1/down-payments-statistics?type=receivable');

        $response->assertOk();
        expect($response->json('total_count'))->toBe(3);
    });
});

describe('Down Payment Applications List', function () {

    it('returns applications for a down payment', function () {
        $downPayment = DownPayment::factory()->receivable()->partiallyApplied(5000000)->create();
        DownPaymentApplication::factory()->forDownPayment($downPayment)->count(3)->create();

        $response = $this->getJson("/api/v1/down-payments/{$downPayment->id}/applications");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });
});

describe('Down Payment Status Updates', function () {

    it('marks as fully applied when all amount is used', function () {
        $contact = Contact::factory()->customer()->create();
        $bankAccount = Account::where('code', '1111')->first();
        $receivableAccount = Account::where('code', '1-1100')->first();

        $downPayment = DownPayment::factory()->receivable()->forContact($contact)->create([
            'amount' => 5000000,
            'cash_account_id' => $bankAccount->id,
        ]);

        $invoice = Invoice::factory()->create([
            'contact_id' => $contact->id,
            'total_amount' => 10000000,
            'paid_amount' => 0,
            'status' => DocumentStatus::Sent,
            'receivable_account_id' => $receivableAccount->id,
        ]);

        $response = $this->postJson("/api/v1/down-payments/{$downPayment->id}/apply-to-invoice/{$invoice->id}", [
            'amount' => 5000000,
        ]);

        $response->assertCreated();

        $downPayment->refresh();
        expect($downPayment->status)->toBe(DownPayment::STATUS_FULLY_APPLIED);
    });
});
