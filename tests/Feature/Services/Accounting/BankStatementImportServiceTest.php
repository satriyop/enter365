<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Accounting\BankTransaction;
use App\Models\User;
use App\Services\Accounting\BankImport\BankStatementImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->service = app(BankStatementImportService::class);

    $this->bankAccount = Account::where('code', '1110')->first()
        ?? Account::factory()->asset()->create(['code' => '1110', 'name' => 'Bank BCA']);
});

describe('detectFormat', function () {
    it('detects BCA format correctly', function () {
        $content = "Tanggal,Keterangan,Cabang,Mutasi,Saldo\n"
            ."01/12/2024,Transfer dari PT ABC,Jakarta,-1000000,5000000\n"
            ."02/12/2024,Setoran tunai,Jakarta,2000000,7000000\n";

        $file = UploadedFile::fake()->createWithContent('bca-statement.csv', $content);

        $result = $this->service->detectFormat($file);

        expect($result)
            ->toHaveKey('bank', 'bca')
            ->toHaveKey('date_format', 'd/m/Y')
            ->toHaveKey('delimiter', ',')
            ->toHaveKey('sample_rows')
            ->and($result['sample_rows'])->toHaveCount(2);
    });

    it('detects Mandiri format correctly', function () {
        $content = "Tanggal;Keterangan;Debet;Kredit;Saldo\n"
            ."01-12-2024;Transfer masuk;;5.000.000;10.000.000\n"
            ."02-12-2024;Pembayaran vendor;3.000.000;;7.000.000\n";

        $file = UploadedFile::fake()->createWithContent('mandiri-statement.csv', $content);

        $result = $this->service->detectFormat($file);

        expect($result)
            ->toHaveKey('bank', 'mandiri')
            ->toHaveKey('date_format', 'd-m-Y')
            ->toHaveKey('delimiter', ';');
    });
});

describe('validateImport', function () {
    it('validates BCA format successfully', function () {
        $content = "Tanggal,Keterangan,Cabang,Mutasi,Saldo\n"
            ."01/12/2024,Transfer dari PT ABC,Jakarta,-1000000,5000000\n"
            ."02/12/2024,Setoran tunai,Jakarta,2000000,7000000\n";

        $file = UploadedFile::fake()->createWithContent('bca-statement.csv', $content);

        $result = $this->service->validateImport($file, $this->bankAccount->id);

        expect($result)
            ->toHaveKey('valid_count', 2)
            ->toHaveKey('error_count', 0)
            ->toHaveKey('duplicate_count', 0)
            ->toHaveKey('preview_rows')
            ->and($result['preview_rows'])->toHaveCount(2);
    });

    it('detects duplicate transactions', function () {
        // Create existing transaction
        BankTransaction::create([
            'account_id' => $this->bankAccount->id,
            'transaction_date' => '2024-12-01',
            'description' => 'Transfer dari PT ABC',
            'debit' => 1000000,
            'credit' => 0,
            'status' => 'unmatched',
            'external_id' => md5('2024-12-01Transfer dari PT ABC10000000'),
            'created_by' => $this->user->id,
        ]);

        $content = "Tanggal,Keterangan,Cabang,Mutasi,Saldo\n"
            ."01/12/2024,Transfer dari PT ABC,Jakarta,-1000000,5000000\n"
            ."02/12/2024,Setoran tunai,Jakarta,2000000,7000000\n";

        $file = UploadedFile::fake()->createWithContent('bca-statement.csv', $content);

        $result = $this->service->validateImport($file, $this->bankAccount->id);

        expect($result)
            ->toHaveKey('valid_count', 1)
            ->toHaveKey('duplicate_count', 1)
            ->and($result['warnings'])->toContain('Baris 2: Transaksi duplikat (sudah ada di database)');
    });

    it('reports parse errors for invalid rows', function () {
        $content = "Tanggal,Keterangan,Cabang,Mutasi,Saldo\n"
            ."INVALID-DATE,Transfer dari PT ABC,Jakarta,-1000000,5000000\n"
            ."02/12/2024,Setoran tunai,Jakarta,2000000,7000000\n";

        $file = UploadedFile::fake()->createWithContent('bca-statement.csv', $content);

        $result = $this->service->validateImport($file, $this->bankAccount->id);

        expect($result)
            ->toHaveKey('valid_count', 1)
            ->toHaveKey('error_count', 1)
            ->and($result['errors'])->not->toBeEmpty();
    });
});

describe('executeImport', function () {
    it('imports BCA transactions successfully', function () {
        $content = "Tanggal,Keterangan,Cabang,Mutasi,Saldo\n"
            ."01/12/2024,Transfer dari PT ABC,Jakarta,-1000000,5000000\n"
            ."02/12/2024,Setoran tunai,Jakarta,2000000,7000000\n";

        $file = UploadedFile::fake()->createWithContent('bca-statement.csv', $content);

        $result = $this->service->executeImport($file, $this->bankAccount->id);

        expect($result)
            ->toHaveKey('import_batch')
            ->toHaveKey('imported_count', 2)
            ->toHaveKey('skipped_count', 0)
            ->and(BankTransaction::where('import_batch', $result['import_batch'])->count())->toBe(2);

        $transactions = BankTransaction::where('import_batch', $result['import_batch'])->get();

        expect($transactions->first())
            ->account_id->toBe($this->bankAccount->id)
            ->debit->toBe(1000000)
            ->credit->toBe(0)
            ->status->value->toBe('unmatched');
    });

    it('skips duplicate transactions during import', function () {
        // Create existing transaction
        BankTransaction::create([
            'account_id' => $this->bankAccount->id,
            'transaction_date' => '2024-12-01',
            'description' => 'Transfer dari PT ABC',
            'debit' => 1000000,
            'credit' => 0,
            'status' => 'unmatched',
            'external_id' => md5('2024-12-01Transfer dari PT ABC10000000'),
            'created_by' => $this->user->id,
        ]);

        $content = "Tanggal,Keterangan,Cabang,Mutasi,Saldo\n"
            ."01/12/2024,Transfer dari PT ABC,Jakarta,-1000000,5000000\n"
            ."02/12/2024,Setoran tunai,Jakarta,2000000,7000000\n";

        $file = UploadedFile::fake()->createWithContent('bca-statement.csv', $content);

        $result = $this->service->executeImport($file, $this->bankAccount->id);

        expect($result)
            ->imported_count->toBe(1)
            ->skipped_count->toBe(1);
    });

    it('handles Mandiri semicolon-delimited format', function () {
        $content = "Tanggal;Keterangan;Debet;Kredit;Saldo\n"
            ."01-12-2024;Transfer masuk;;5.000.000;10.000.000\n"
            ."02-12-2024;Pembayaran vendor;3.000.000;;7.000.000\n";

        $file = UploadedFile::fake()->createWithContent('mandiri-statement.csv', $content);

        $result = $this->service->executeImport($file, $this->bankAccount->id);

        expect($result)
            ->imported_count->toBe(2);

        $transactions = BankTransaction::where('import_batch', $result['import_batch'])
            ->orderBy('transaction_date')
            ->get();

        expect($transactions->first())
            ->debit->toBe(0)
            ->credit->toBe(5000000);

        expect($transactions->last())
            ->debit->toBe(3000000)
            ->credit->toBe(0);
    });
});

describe('deleteBatch', function () {
    it('deletes unreconciled batch successfully', function () {
        $batch = (string) \Illuminate\Support\Str::uuid();

        BankTransaction::create([
            'account_id' => $this->bankAccount->id,
            'transaction_date' => now()->toDateString(),
            'description' => 'Test transaction',
            'debit' => 1000000,
            'credit' => 0,
            'status' => 'unmatched',
            'import_batch' => $batch,
            'external_id' => md5('test1'),
            'created_by' => $this->user->id,
        ]);

        $count = $this->service->deleteBatch($batch);

        expect($count)->toBe(1)
            ->and(BankTransaction::where('import_batch', $batch)->count())->toBe(0);
    });

    it('refuses to delete batch with reconciled transactions', function () {
        $batch = (string) \Illuminate\Support\Str::uuid();

        BankTransaction::create([
            'account_id' => $this->bankAccount->id,
            'transaction_date' => now()->toDateString(),
            'description' => 'Reconciled transaction',
            'debit' => 1000000,
            'credit' => 0,
            'status' => 'reconciled',
            'import_batch' => $batch,
            'external_id' => md5('test2'),
            'reconciled_at' => now(),
            'reconciled_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        expect(fn () => $this->service->deleteBatch($batch))
            ->toThrow(RuntimeException::class);
    });
});
