<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Accounting\BankTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);

    // Authenticate as admin (has all permissions)
    authenticatedAdmin();

    $this->bankAccount = Account::where('code', '1110')->first()
        ?? Account::factory()->asset()->create(['code' => '1110', 'name' => 'Bank BCA']);
});

describe('POST /api/v1/bank-statements/detect-format', function () {
    it('detects BCA format via API', function () {
        $content = "Tanggal,Keterangan,Cabang,Mutasi,Saldo\n"
            ."01/12/2024,Transfer dari PT ABC,Jakarta,-1000000,5000000\n";

        $file = UploadedFile::fake()->createWithContent('bca-statement.csv', $content);

        $response = $this->postJson('/api/v1/bank-statements/detect-format', [
            'file' => $file,
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'bank',
                    'date_format',
                    'delimiter',
                    'sample_rows',
                ],
            ])
            ->assertJson([
                'data' => [
                    'bank' => 'bca',
                    'date_format' => 'd/m/Y',
                ],
            ]);
    });

    it('rejects non-CSV files', function () {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->postJson('/api/v1/bank-statements/detect-format', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });
});

describe('POST /api/v1/bank-statements/validate', function () {
    it('validates import successfully', function () {
        $content = "Tanggal,Keterangan,Cabang,Mutasi,Saldo\n"
            ."01/12/2024,Transfer dari PT ABC,Jakarta,-1000000,5000000\n"
            ."02/12/2024,Setoran tunai,Jakarta,2000000,7000000\n";

        $file = UploadedFile::fake()->createWithContent('bca-statement.csv', $content);

        $response = $this->postJson('/api/v1/bank-statements/validate', [
            'file' => $file,
            'account_id' => $this->bankAccount->id,
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'valid_count',
                    'error_count',
                    'duplicate_count',
                    'preview_rows',
                    'errors',
                    'warnings',
                ],
            ])
            ->assertJson([
                'data' => [
                    'valid_count' => 2,
                    'error_count' => 0,
                ],
            ]);
    });

    it('requires account_id', function () {
        $content = "Tanggal,Keterangan,Cabang,Mutasi,Saldo\n"
            ."01/12/2024,Transfer dari PT ABC,Jakarta,-1000000,5000000\n";

        $file = UploadedFile::fake()->createWithContent('bca-statement.csv', $content);

        $response = $this->postJson('/api/v1/bank-statements/validate', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);
    });
});

describe('POST /api/v1/bank-statements/import', function () {
    it('imports transactions successfully', function () {
        $content = "Tanggal,Keterangan,Cabang,Mutasi,Saldo\n"
            ."01/12/2024,Transfer dari PT ABC,Jakarta,-1000000,5000000\n"
            ."02/12/2024,Setoran tunai,Jakarta,2000000,7000000\n";

        $file = UploadedFile::fake()->createWithContent('bca-statement.csv', $content);

        $response = $this->postJson('/api/v1/bank-statements/import', [
            'file' => $file,
            'account_id' => $this->bankAccount->id,
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'import_batch',
                    'imported_count',
                    'skipped_count',
                    'errors',
                ],
            ])
            ->assertJson([
                'data' => [
                    'imported_count' => 2,
                    'skipped_count' => 0,
                ],
            ]);

        expect(BankTransaction::where('account_id', $this->bankAccount->id)->count())->toBe(2);
    });
});

describe('GET /api/v1/bank-statements/batches', function () {
    it('lists import batches', function () {
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
            'created_by' => auth()->id(),
        ]);

        $response = $this->getJson('/api/v1/bank-statements/batches');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'import_batch',
                        'account_id',
                        'first_date',
                        'last_date',
                        'transaction_count',
                        'total_debit',
                        'total_credit',
                        'reconciled_count',
                    ],
                ],
            ]);
    });
});

describe('DELETE /api/v1/bank-statements/batches/{batch}', function () {
    it('deletes batch successfully', function () {
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
            'created_by' => auth()->id(),
        ]);

        $response = $this->deleteJson("/api/v1/bank-statements/batches/{$batch}");

        $response->assertSuccessful()
            ->assertJson([
                'message' => '1 transaksi berhasil dihapus.',
            ]);

        expect(BankTransaction::where('import_batch', $batch)->count())->toBe(0);
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
            'reconciled_by' => auth()->id(),
            'created_by' => auth()->id(),
        ]);

        $response = $this->deleteJson("/api/v1/bank-statements/batches/{$batch}");

        $response->assertStatus(422);
    });
});
