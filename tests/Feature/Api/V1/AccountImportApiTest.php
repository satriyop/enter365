<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    authenticatedAdmin();
});

function coaCsv(string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent('coa-import.csv', $content);
}

describe('POST /api/v1/accounts/import', function () {

    it('imports valid CoA rows from CSV', function () {
        $parent = Account::factory()->create([
            'code' => '1-9000',
            'name' => 'Parent Asset',
            'type' => Account::TYPE_ASSET,
        ]);

        $csv = "code,name,type,subtype,parent,active,allow_reconciliation,currency\n"
            ."1-9001,Imported Cash,asset,current_asset,{$parent->code},1,1,USD\n"
            ."1-9002,Imported Receivable,asset,current_asset,{$parent->id},true,false,\n";

        $response = $this->postJson('/api/v1/accounts/import', [
            'file' => coaCsv($csv),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.created_count', 2)
            ->assertJsonPath('data.error_count', 0);

        $this->assertDatabaseHas('accounts', [
            'code' => '1-9001',
            'name' => 'Imported Cash',
            'type' => Account::TYPE_ASSET,
            'parent_id' => $parent->id,
            'allow_reconciliation' => true,
            'currency' => 'USD',
        ]);

        $this->assertDatabaseHas('accounts', [
            'code' => '1-9002',
            'name' => 'Imported Receivable',
            'parent_id' => $parent->id,
            'allow_reconciliation' => false,
        ]);
    });

    it('creates successful rows and reports validation failures for bad rows', function () {
        $assetParent = Account::factory()->create([
            'code' => '1-9100',
            'name' => 'Asset Parent',
            'type' => Account::TYPE_ASSET,
        ]);

        $existing = Account::where('code', '1-1001')->first();

        $csv = "code,name,type,subtype,parent,active,allow_reconciliation,currency\n"
            ."1-9101,Good Account,asset,current_asset,{$assetParent->code},1,0,IDR\n"
            .($existing?->code ?? '1-1001').",Duplicate Code,asset,current_asset,,,0,\n"
            ."1-9102,Bad Type,not_a_type,,,,,\n"
            ."1-9103,Wrong Parent Type,liability,current_liability,{$assetParent->code},1,0,\n"
            ."1-9104,Missing Parent,asset,current_asset,NO-SUCH-PARENT,1,0,\n"
            ."1-9105,Bad Currency,asset,current_asset,,,0,XXX\n";

        $response = $this->postJson('/api/v1/accounts/import', [
            'file' => coaCsv($csv),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.error_count', 5);

        $this->assertDatabaseHas('accounts', ['code' => '1-9101', 'name' => 'Good Account']);
        $this->assertDatabaseMissing('accounts', ['code' => '1-9102']);
        $this->assertDatabaseMissing('accounts', ['code' => '1-9103']);
        $this->assertDatabaseMissing('accounts', ['code' => '1-9104']);
        $this->assertDatabaseMissing('accounts', ['code' => '1-9105']);

        $errors = collect($response->json('data.errors'));
        expect($errors->pluck('row')->all())->toContain(3, 4, 5, 6, 7);

        $flat = $errors->flatMap(fn ($e) => $e['messages'])->implode(' ');
        expect($flat)->toContain('Kode akun sudah digunakan.');
        expect($flat)->toContain('Tipe akun tidak valid.');
        expect($flat)->toContain('Tipe akun induk harus sama dengan tipe akun yang dibuat.');
        expect($flat)->toContain("Akun induk 'NO-SUCH-PARENT' tidak ditemukan.");
    });

    it('returns 422 when every row fails validation', function () {
        $csv = "code,name,type,subtype,parent,active,allow_reconciliation,currency\n"
            ."1-1001,Dup,asset,,,,,,\n"
            .",Missing Code,asset,,,,,,\n";

        $response = $this->postJson('/api/v1/accounts/import', [
            'file' => coaCsv($csv),
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('data.created_count', 0)
            ->assertJsonPath('message', 'Tidak ada akun valid untuk diimport.');
    });

    it('rejects missing or unsupported file types', function () {
        $response = $this->postJson('/api/v1/accounts/import', []);
        $response->assertUnprocessable()->assertJsonValidationErrors(['file']);

        $pdf = UploadedFile::fake()->create('accounts.pdf', 100, 'application/pdf');
        $response = $this->postJson('/api/v1/accounts/import', ['file' => $pdf]);
        $response->assertUnprocessable()->assertJsonValidationErrors(['file']);
    });

    it('allows parent codes created earlier in the same file', function () {
        $csv = "code,name,type,subtype,parent,active,allow_reconciliation,currency\n"
            ."1-9200,New Parent,asset,current_asset,,1,0,\n"
            ."1-9201,Child Of New,asset,current_asset,1-9200,1,0,\n";

        $response = $this->postJson('/api/v1/accounts/import', [
            'file' => coaCsv($csv),
        ]);

        $response->assertCreated()->assertJsonPath('data.created_count', 2);

        $parent = Account::where('code', '1-9200')->first();
        $child = Account::where('code', '1-9201')->first();

        expect($parent)->not->toBeNull();
        expect($child?->parent_id)->toBe($parent->id);
    });
});
