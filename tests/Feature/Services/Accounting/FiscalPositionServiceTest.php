<?php

use App\Contracts\Accounting\FiscalPositionServiceInterface;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalPosition;
use App\Models\Contacts\Contact;
use App\Models\Tax\TaxRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    authenticatedAdmin();
});

describe('FiscalPositionService mapping', function () {
    it('replaces mapped taxes, drops exempt dest, and leaves unmapped taxes', function () {
        $ppn = TaxRecord::factory()->create(['code' => 'PPN-M', 'rate' => 11]);
        $zero = TaxRecord::factory()->create(['code' => 'PPN-Z', 'rate' => 0]);
        $luxury = TaxRecord::factory()->create(['code' => 'PPnBM-M', 'rate' => 1]);
        $exemptSource = TaxRecord::factory()->create(['code' => 'PPN-X', 'rate' => 11]);

        $position = FiscalPosition::factory()->create();
        $position->taxMaps()->create([
            'source_tax_record_id' => $ppn->id,
            'dest_tax_record_id' => $zero->id,
        ]);
        $position->taxMaps()->create([
            'source_tax_record_id' => $exemptSource->id,
            'dest_tax_record_id' => null,
        ]);
        $position->load('taxMaps');

        $service = app(FiscalPositionServiceInterface::class);

        expect($service->mapTaxRecordIds($position, [$ppn->id, $luxury->id, $exemptSource->id]))
            ->toBe([$zero->id, $luxury->id]);
    });

    it('maps accounts one hop and leaves unmapped accounts', function () {
        $source = Account::factory()->revenue()->create();
        $dest = Account::factory()->revenue()->create();
        $other = Account::factory()->revenue()->create();

        $position = FiscalPosition::factory()->create();
        $position->accountMaps()->create([
            'source_account_id' => $source->id,
            'dest_account_id' => $dest->id,
        ]);
        $position->load('accountMaps');

        $service = app(FiscalPositionServiceInterface::class);

        expect($service->mapAccountId($position, $source->id))->toBe($dest->id)
            ->and($service->mapAccountId($position, $other->id))->toBe($other->id)
            ->and($service->mapAccountId(null, $source->id))->toBe($source->id);
    });

    it('resolves the contact fiscal position with maps', function () {
        $position = FiscalPosition::factory()->create();
        $contact = Contact::factory()->create(['fiscal_position_id' => $position->id]);

        $service = app(FiscalPositionServiceInterface::class);

        $resolved = $service->forContactId($contact->id);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($position->id);
    });

    it('refuses to delete a position assigned to contacts', function () {
        $position = FiscalPosition::factory()->create();
        Contact::factory()->create(['fiscal_position_id' => $position->id]);

        $service = app(FiscalPositionServiceInterface::class);

        expect(fn () => $service->delete($position))
            ->toThrow(BusinessRuleException::class, 'Posisi fiskal tidak bisa dihapus karena masih dipakai kontak.');
    });
});
