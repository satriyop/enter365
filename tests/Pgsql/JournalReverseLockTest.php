<?php

declare(strict_types=1);

use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\JournalService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\PostgresRowLock;

describe('Journal reverseEntry under a held row lock', function () {
    it('cannot reverse a posted entry while another session holds it', function () {
        authenticatedAdmin();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);

        $service = app(JournalService::class);
        $entry = $service->createEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Lock fixture',
            'lines' => [
                ['account_code' => '1-1000', 'debit' => 1000, 'credit' => 0],
                ['account_code' => '4-1001', 'debit' => 0, 'credit' => 1000],
            ],
        ]);
        $service->postEntry($entry);

        DB::beginTransaction();

        try {
            JournalEntry::query()->lockForUpdate()->findOrFail($entry->id);

            PostgresRowLock::onPeer(function () use ($service, $entry): void {
                try {
                    $service->reverseEntry($entry, 'Race reverse');
                    test()->fail('reverseEntry completed while the journal row was locked by another session.');
                } catch (QueryException $exception) {
                    expect(PostgresRowLock::isLockTimeout($exception))->toBeTrue(
                        $exception->getMessage()
                    );
                }
            });
        } finally {
            DB::rollBack();
        }

        $reversal = $service->reverseEntry($entry, 'After lock released');
        expect($entry->fresh()->is_reversed)->toBeTrue()
            ->and($reversal->reversal_of_id)->toBe($entry->id);
    });
});
