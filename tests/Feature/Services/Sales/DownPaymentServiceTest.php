<?php

declare(strict_types=1);

use App\Contracts\Sales\DownPaymentServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Models\Sales\DownPayment;
use App\Models\Sales\DownPaymentApplication;
use App\Models\Sales\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->service = app(DownPaymentServiceInterface::class);

    $this->customer = Contact::factory()->customer()->create();
    $this->bankAccount = Account::where('code', '1110')->first()
        ?? Account::factory()->create(['code' => '1110']);
});

describe('create', function () {
    it('creates receivable down payment', function () {
        $dp = $this->service->create([
            'type' => DownPayment::TYPE_RECEIVABLE,
            'contact_id' => $this->customer->id,
            'dp_date' => now()->toDateString(),
            'amount' => 10000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
        ]);

        expect($dp)
            ->toBeInstanceOf(DownPayment::class)
            ->dp_number->toStartWith('DPR-')
            ->type->toBe(DownPayment::TYPE_RECEIVABLE)
            ->amount->toBe(10000000)
            ->applied_amount->toBe(0)
            ->journal_entry_id->not->toBeNull();
    });

    it('creates payable down payment', function () {
        $vendor = Contact::factory()->vendor()->create();

        $dp = $this->service->create([
            'type' => DownPayment::TYPE_PAYABLE,
            'contact_id' => $vendor->id,
            'dp_date' => now()->toDateString(),
            'amount' => 5000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
        ]);

        expect($dp)
            ->toBeInstanceOf(DownPayment::class)
            ->dp_number->toStartWith('DPP-')
            ->type->toBe(DownPayment::TYPE_PAYABLE);
    });
});

describe('update', function () {
    it('updates active down payment with no applications', function () {
        $dp = $this->service->create([
            'type' => DownPayment::TYPE_RECEIVABLE,
            'contact_id' => $this->customer->id,
            'dp_date' => now()->toDateString(),
            'amount' => 10000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
        ]);

        $updated = $this->service->update($dp, [
            'description' => 'Updated description',
        ]);

        expect($updated->description)->toBe('Updated description');
    });

    it('throws exception when updating down payment with applications', function () {
        $dp = DownPayment::factory()
            ->receivable()
            ->forContact($this->customer)
            ->withAmount(10000000)
            ->create(['cash_account_id' => $this->bankAccount->id]);

        // Create application directly in DB
        DownPaymentApplication::factory()->create([
            'down_payment_id' => $dp->id,
            'applicable_type' => Invoice::class,
            'applicable_id' => Invoice::factory()->create(['contact_id' => $this->customer->id])->id,
            'amount' => 5000000,
        ]);

        expect(fn () => $this->service->update($dp, ['description' => 'test']))
            ->toThrow(DocumentLockedException::class);
    });

    it('throws exception when updating non-active down payment', function () {
        $dp = DownPayment::factory()
            ->receivable()
            ->cancelled()
            ->forContact($this->customer)
            ->create(['cash_account_id' => $this->bankAccount->id]);

        expect(fn () => $this->service->update($dp, ['description' => 'test']))
            ->toThrow(StateTransitionException::class);
    });
});

describe('delete', function () {
    it('deletes down payment with no applications', function () {
        $dp = $this->service->create([
            'type' => DownPayment::TYPE_RECEIVABLE,
            'contact_id' => $this->customer->id,
            'dp_date' => now()->toDateString(),
            'amount' => 10000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
        ]);

        $result = $this->service->delete($dp);

        expect($result)->toBeTrue()
            ->and(DownPayment::find($dp->id))->toBeNull();
    });

    it('throws exception when deleting down payment with applications', function () {
        $dp = DownPayment::factory()
            ->receivable()
            ->forContact($this->customer)
            ->create(['cash_account_id' => $this->bankAccount->id]);

        DownPaymentApplication::factory()->create([
            'down_payment_id' => $dp->id,
            'applicable_type' => Invoice::class,
            'applicable_id' => Invoice::factory()->create(['contact_id' => $this->customer->id])->id,
            'amount' => 5000000,
        ]);

        expect(fn () => $this->service->delete($dp))
            ->toThrow(DocumentLockedException::class);
    });
});

describe('cancel', function () {
    it('cancels active down payment', function () {
        $dp = $this->service->create([
            'type' => DownPayment::TYPE_RECEIVABLE,
            'contact_id' => $this->customer->id,
            'dp_date' => now()->toDateString(),
            'amount' => 10000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
        ]);

        $result = $this->service->cancel($dp, 'Dibatalkan karena perubahan kontrak');

        expect($result->status)->toBe(DocumentStatus::Cancelled);
    });

    it('throws exception when cancelling non-active down payment', function () {
        $dp = DownPayment::factory()
            ->receivable()
            ->cancelled()
            ->forContact($this->customer)
            ->create(['cash_account_id' => $this->bankAccount->id]);

        expect(fn () => $this->service->cancel($dp))
            ->toThrow(StateTransitionException::class);
    });
});

describe('getAvailableForContact', function () {
    it('returns available down payments for contact', function () {
        // Active with remaining balance
        DownPayment::factory()
            ->receivable()
            ->forContact($this->customer)
            ->withAmount(10000000)
            ->count(2)
            ->create(['cash_account_id' => $this->bankAccount->id]);

        // Fully applied - should not be returned
        DownPayment::factory()
            ->receivable()
            ->fullyApplied()
            ->forContact($this->customer)
            ->create(['cash_account_id' => $this->bankAccount->id]);

        // Different contact - should not be returned
        DownPayment::factory()->receivable()->create(['cash_account_id' => $this->bankAccount->id]);

        $available = $this->service->getAvailableForContact(
            $this->customer->id,
            DownPayment::TYPE_RECEIVABLE
        );

        expect($available)->toHaveCount(2);
    });

    it('returns empty collection when no available down payments', function () {
        $available = $this->service->getAvailableForContact(
            $this->customer->id,
            DownPayment::TYPE_RECEIVABLE
        );

        expect($available)->toHaveCount(0);
    });
});
