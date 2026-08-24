<?php

declare(strict_types=1);

use App\Domain\Shared\DocumentNumbers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Insert a journal entry carrying a given number, bypassing the generator. */
function seedEntryNumber(string $number): void
{
    DB::table('journal_entries')->insert([
        'entry_number' => $number,
        'entry_date' => now()->toDateString(),
        'description' => 'seed',
        'is_posted' => false,
        'is_reversed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function generateEntryNumber(string $prefix): string
{
    return DocumentNumbers::generate($prefix, 'journal_entries', 'entry_number');
}

describe('DocumentNumbers', function () {
    it('starts at 0001 for an unused prefix', function () {
        expect(generateEntryNumber('JE-202608-'))->toBe('JE-202608-0001');
    });

    it('increments on each call', function () {
        expect(generateEntryNumber('JE-202608-'))->toBe('JE-202608-0001')
            ->and(generateEntryNumber('JE-202608-'))->toBe('JE-202608-0002')
            ->and(generateEntryNumber('JE-202608-'))->toBe('JE-202608-0003');
    });

    it('keeps separate counters per prefix', function () {
        expect(generateEntryNumber('JE-202608-'))->toBe('JE-202608-0001')
            ->and(generateEntryNumber('JE-202609-'))->toBe('JE-202609-0001')
            ->and(generateEntryNumber('JE-202608-'))->toBe('JE-202608-0002');
    });

    it('seeds from numbers that already exist for the prefix', function () {
        seedEntryNumber('JE-202608-0007');

        expect(generateEntryNumber('JE-202608-'))->toBe('JE-202608-0008');
    });

    it('ranks existing numbers numerically, not as text', function () {
        // '9999' sorts above '10000' as text — the defect that made the old
        // implementation hand out a duplicate forever.
        seedEntryNumber('JE-202608-9999');
        seedEntryNumber('JE-202608-10000');

        expect(generateEntryNumber('JE-202608-'))->toBe('JE-202608-10001');
    });

    it('crosses 9999 without collapsing back to 0001', function () {
        DB::table('document_sequences')->insert([
            'prefix' => 'JE-202608-',
            'next_value' => 9998,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(generateEntryNumber('JE-202608-'))->toBe('JE-202608-9999')
            ->and(generateEntryNumber('JE-202608-'))->toBe('JE-202608-10000')
            ->and(generateEntryNumber('JE-202608-'))->toBe('JE-202608-10001')
            ->and(generateEntryNumber('JE-202608-'))->toBe('JE-202608-10002');
    });

    it('never repeats a number across a long run spanning the 10000 boundary', function () {
        DB::table('document_sequences')->insert([
            'prefix' => 'JE-202608-',
            'next_value' => 9990,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $generated = [];
        for ($i = 0; $i < 25; $i++) {
            $generated[] = generateEntryNumber('JE-202608-');
        }

        expect($generated)->toHaveCount(25)
            ->and(array_unique($generated))->toHaveCount(25);
    });

    it('ignores non-numeric suffixes when seeding', function () {
        seedEntryNumber('JE-202608-MANUAL');
        seedEntryNumber('JE-202608-0004');

        expect(generateEntryNumber('JE-202608-'))->toBe('JE-202608-0005');
    });

    it('skips ahead when the sequence row lags numbers already in the table', function () {
        DB::table('document_sequences')->insert([
            'prefix' => 'JE-202608-',
            'next_value' => 66,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        seedEntryNumber('JE-202608-0067');
        seedEntryNumber('JE-202608-0072');

        expect(generateEntryNumber('JE-202608-'))->toBe('JE-202608-0073')
            ->and(generateEntryNumber('JE-202608-'))->toBe('JE-202608-0074');
    });

    it('does not rescan existing numbers on the happy path', function () {
        generateEntryNumber('JE-202608-');

        DB::enableQueryLog();
        generateEntryNumber('JE-202608-');
        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' ');
        DB::disableQueryLog();

        expect($queries)->not->toContain('like');
    });
});
