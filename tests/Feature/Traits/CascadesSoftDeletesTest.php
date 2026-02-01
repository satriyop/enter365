<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Inventory\ProductCategory;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Sales\DownPayment;
use App\Models\Sales\DownPaymentApplication;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->bankAccount = Account::where('code', '1110')->first()
        ?? Account::factory()->create(['code' => '1110']);
});

describe('Invoice → payments cascade', function () {
    it('soft deletes payments when invoice is soft deleted', function () {
        $invoice = Invoice::factory()->create();
        // Must use morph alias since enforceMorphMap is active
        $payment = Payment::factory()->create([
            'payable_type' => 'invoice',
            'payable_id' => $invoice->id,
            'cash_account_id' => $this->bankAccount->id,
        ]);

        $invoice->delete();

        expect(Invoice::find($invoice->id))->toBeNull()
            ->and(Payment::find($payment->id))->toBeNull()
            ->and(Invoice::withTrashed()->find($invoice->id))->not->toBeNull()
            ->and(Payment::withTrashed()->find($payment->id))->not->toBeNull();
    });

    it('restores payments when invoice is restored', function () {
        $invoice = Invoice::factory()->create();
        $payment = Payment::factory()->create([
            'payable_type' => 'invoice',
            'payable_id' => $invoice->id,
            'cash_account_id' => $this->bankAccount->id,
        ]);

        $invoice->delete();
        $invoice->restore();

        expect(Invoice::find($invoice->id))->not->toBeNull()
            ->and(Payment::find($payment->id))->not->toBeNull();
    });

    it('cascades to multiple payments', function () {
        $invoice = Invoice::factory()->create();
        Payment::factory()->count(3)->create([
            'payable_type' => 'invoice',
            'payable_id' => $invoice->id,
            'cash_account_id' => $this->bankAccount->id,
        ]);

        $invoice->delete();

        expect($invoice->payments()->count())->toBe(0)
            ->and($invoice->payments()->withTrashed()->count())->toBe(3);
    });
});

describe('DownPayment → applications cascade', function () {
    it('soft deletes applications when down payment is soft deleted', function () {
        $dp = DownPayment::factory()->receivable()->create([
            'cash_account_id' => $this->bankAccount->id,
        ]);
        $application = DownPaymentApplication::factory()->create([
            'down_payment_id' => $dp->id,
            'applicable_type' => Invoice::class,
            'applicable_id' => Invoice::factory()->create()->id,
            'amount' => 1000000,
        ]);

        $dp->delete();

        expect(DownPayment::find($dp->id))->toBeNull()
            ->and(DownPaymentApplication::find($application->id))->toBeNull()
            ->and(DownPaymentApplication::withTrashed()->find($application->id))->not->toBeNull();
    });

    it('restores applications when down payment is restored', function () {
        $dp = DownPayment::factory()->receivable()->create([
            'cash_account_id' => $this->bankAccount->id,
        ]);
        $application = DownPaymentApplication::factory()->create([
            'down_payment_id' => $dp->id,
            'applicable_type' => Invoice::class,
            'applicable_id' => Invoice::factory()->create()->id,
            'amount' => 1000000,
        ]);

        $dp->delete();
        $dp->restore();

        expect(DownPaymentApplication::find($application->id))->not->toBeNull();
    });
});

describe('Bom → childBoms recursive cascade', function () {
    it('soft deletes child BOMs when parent BOM is soft deleted', function () {
        $parentBom = Bom::factory()->create();
        $childBom = Bom::factory()->create(['parent_bom_id' => $parentBom->id]);

        $parentBom->delete();

        expect(Bom::find($parentBom->id))->toBeNull()
            ->and(Bom::find($childBom->id))->toBeNull()
            ->and(Bom::withTrashed()->find($childBom->id))->not->toBeNull();
    });

    it('recursively cascades through multiple levels', function () {
        $grandparent = Bom::factory()->create();
        $parent = Bom::factory()->create(['parent_bom_id' => $grandparent->id]);
        $child = Bom::factory()->create(['parent_bom_id' => $parent->id]);

        $grandparent->delete();

        expect(Bom::find($grandparent->id))->toBeNull()
            ->and(Bom::find($parent->id))->toBeNull()
            ->and(Bom::find($child->id))->toBeNull()
            ->and(Bom::withTrashed()->find($child->id))->not->toBeNull();
    });

    it('restores child BOMs when parent BOM is restored', function () {
        $parentBom = Bom::factory()->create();
        $childBom = Bom::factory()->create(['parent_bom_id' => $parentBom->id]);

        $parentBom->delete();
        $parentBom->restore();

        expect(Bom::find($parentBom->id))->not->toBeNull()
            ->and(Bom::find($childBom->id))->not->toBeNull();
    });

    it('recursively restores through multiple levels', function () {
        $grandparent = Bom::factory()->create();
        $parent = Bom::factory()->create(['parent_bom_id' => $grandparent->id]);
        $child = Bom::factory()->create(['parent_bom_id' => $parent->id]);

        $grandparent->delete();
        $grandparent->restore();

        expect(Bom::find($grandparent->id))->not->toBeNull()
            ->and(Bom::find($parent->id))->not->toBeNull()
            ->and(Bom::find($child->id))->not->toBeNull();
    });
});

describe('ProductCategory → children recursive cascade', function () {
    it('soft deletes child categories when parent is soft deleted', function () {
        $parent = ProductCategory::factory()->create();
        $child = ProductCategory::factory()->create(['parent_id' => $parent->id]);
        $grandchild = ProductCategory::factory()->create(['parent_id' => $child->id]);

        $parent->delete();

        expect(ProductCategory::find($parent->id))->toBeNull()
            ->and(ProductCategory::find($child->id))->toBeNull()
            ->and(ProductCategory::find($grandchild->id))->toBeNull();
    });

    it('restores child categories when parent is restored', function () {
        $parent = ProductCategory::factory()->create();
        $child = ProductCategory::factory()->create(['parent_id' => $parent->id]);

        $parent->delete();
        $parent->restore();

        expect(ProductCategory::find($child->id))->not->toBeNull();
    });
});

describe('WorkOrder → subWorkOrders recursive cascade', function () {
    it('soft deletes sub work orders when parent is soft deleted', function () {
        $parent = WorkOrder::factory()->create();
        $child = WorkOrder::factory()->create(['parent_work_order_id' => $parent->id]);

        $parent->delete();

        expect(WorkOrder::find($parent->id))->toBeNull()
            ->and(WorkOrder::find($child->id))->toBeNull()
            ->and(WorkOrder::withTrashed()->find($child->id))->not->toBeNull();
    });

    it('restores sub work orders when parent is restored', function () {
        $parent = WorkOrder::factory()->create();
        $child = WorkOrder::factory()->create(['parent_work_order_id' => $parent->id]);

        $parent->delete();
        $parent->restore();

        expect(WorkOrder::find($child->id))->not->toBeNull();
    });
});

describe('does not cascade on force delete', function () {
    it('skips cascade trait on force delete (DB handles it)', function () {
        $parentBom = Bom::factory()->create();
        $childBom = Bom::factory()->create(['parent_bom_id' => $parentBom->id]);

        // Force delete bypasses the cascade trait
        $parentBom->forceDelete();

        expect(Bom::withTrashed()->find($parentBom->id))->toBeNull();
    });
});
