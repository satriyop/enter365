<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generates gapless, per-prefix sequential document numbers.
 *
 * Numbers are claimed from a counter row in `document_sequences`, one row per
 * prefix, using a single atomic statement. The previous implementation derived
 * the next value by string-sorting the target table and parsing the last four
 * characters of the highest number, which had three defects:
 *
 *   - `str_pad` does not truncate, so document 10,000 became a five-digit
 *     suffix; `substr($last, -4)` then read "0000" and reset the counter.
 *   - Descending *string* order ranks "…-9999" above "…-10000" ('9' > '1'), so
 *     the highest number stayed 9999 forever and every later generation
 *     collided on the unique index. The document type became permanently
 *     unable to create records until the month rolled over.
 *   - `lockForUpdate()` locks nothing when the LIKE matches no rows, so two
 *     concurrent first-of-month writes both produced "0001".
 *
 * The suffix width is now cosmetic: correctness no longer depends on parsing a
 * formatted number back into an integer, so exceeding it is harmless.
 *
 * Claiming holds a row lock for the remainder of the caller's transaction,
 * which serialises concurrent creation within a prefix. That is the price of
 * gapless numbering, and gapless is what Indonesian document numbering wants.
 */
class DocumentNumbers
{
    /** Minimum width of the numeric suffix. Exceeding it is safe. */
    private const DEFAULT_PAD = 4;

    public static function generate(
        string $prefix,
        string $table,
        string $column,
        int $pad = self::DEFAULT_PAD
    ): string {
        // Only open a transaction if the caller has not already; the claim must
        // be atomic but nesting a fresh one would defeat the caller's rollback.
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn () => self::doGenerate($prefix, $table, $column, $pad));
        }

        return self::doGenerate($prefix, $table, $column, $pad);
    }

    private static function doGenerate(string $prefix, string $table, string $column, int $pad): string
    {
        $next = self::claimNext($prefix, $table, $column);

        return $prefix.str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
    }

    /**
     * Atomically claim the next value for a prefix, seeding the counter on
     * first use from any numbers that already exist in the target table.
     */
    private static function claimNext(string $prefix, string $table, string $column): int
    {
        $next = self::incrementSequence($prefix);

        if ($next !== null) {
            return $next;
        }

        DB::table('document_sequences')->insertOrIgnore([
            'prefix' => $prefix,
            'next_value' => self::highestExisting($prefix, $table, $column),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $next = self::incrementSequence($prefix);

        if ($next === null) {
            throw new RuntimeException("Gagal membuat nomor dokumen untuk awalan '{$prefix}'.");
        }

        return $next;
    }

    /**
     * Increment and read back in one statement. Returns null when the prefix
     * has no counter row yet.
     */
    private static function incrementSequence(string $prefix): ?int
    {
        if (self::supportsReturning()) {
            $row = DB::selectOne(
                'update document_sequences set next_value = next_value + 1, updated_at = ? where prefix = ? returning next_value',
                [now(), $prefix]
            );

            return $row === null ? null : (int) $row->next_value;
        }

        // MySQL has no RETURNING; take the row lock explicitly instead.
        $current = DB::table('document_sequences')
            ->where('prefix', $prefix)
            ->lockForUpdate()
            ->value('next_value');

        if ($current === null) {
            return null;
        }

        $next = (int) $current + 1;

        DB::table('document_sequences')
            ->where('prefix', $prefix)
            ->update(['next_value' => $next, 'updated_at' => now()]);

        return $next;
    }

    private static function supportsReturning(): bool
    {
        // PostgreSQL has always supported it; SQLite since 3.35.
        return in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true);
    }

    /**
     * Highest numeric suffix already present for this prefix.
     *
     * Compared numerically rather than as text, so a five-digit suffix ranks
     * above a four-digit one. Suffixes that are not purely numeric (hand-edited
     * or imported numbers) are ignored rather than silently parsed.
     */
    private static function highestExisting(string $prefix, string $table, string $column): int
    {
        $highest = 0;
        $prefixLength = strlen($prefix);

        DB::table($table)
            ->where($column, 'like', $prefix.'%')
            ->orderBy('id')
            ->pluck($column)
            ->each(function ($number) use ($prefixLength, &$highest): void {
                $suffix = substr((string) $number, $prefixLength);

                if ($suffix !== '' && ctype_digit($suffix)) {
                    $highest = max($highest, (int) $suffix);
                }
            });

        return $highest;
    }
}
