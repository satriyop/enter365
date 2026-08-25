<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Prove SELECT … FOR UPDATE with a second PDO session.
 *
 * RefreshDatabase wraps the test in a transaction, so a second connection
 * cannot see the fixture rows. These helpers require committed data
 * (DatabaseTruncation / DatabaseMigrations).
 */
final class PostgresRowLock
{
    public const PEER = 'pgsql_lock_peer';

    public static function skipUnlessPgsql(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            test()->markTestSkipped('F-14 requires PostgreSQL; lockForUpdate() is a no-op on SQLite.');
        }
    }

    public static function peer(): Connection
    {
        $default = (string) config('database.default');
        config(['database.connections.'.self::PEER => config('database.connections.'.$default)]);
        DB::purge(self::PEER);

        $peer = DB::connection(self::PEER);
        $peer->statement("SET lock_timeout = '500ms'");

        return $peer;
    }

    /**
     * Hold FOR UPDATE on $table.id on the default connection, then assert a
     * second session cannot take that lock before the timeout.
     *
     * Fails if lockForUpdate is ignored (SQLite, or a missing FOR UPDATE).
     */
    public static function assertForUpdateBlocks(string $table, int $id): void
    {
        DB::beginTransaction();

        try {
            $row = DB::table($table)->where('id', $id)->lockForUpdate()->first();
            expect($row)->not->toBeNull();

            $peer = self::peer();
            $peer->beginTransaction();

            try {
                $peer->table($table)->where('id', $id)->lockForUpdate()->first();
                test()->fail("Second session locked {$table}.{$id} — SELECT FOR UPDATE did not block.");
            } catch (QueryException $exception) {
                expect(self::isLockTimeout($exception))->toBeTrue(
                    'Expected lock timeout, got: '.$exception->getMessage()
                );
            } finally {
                $peer->rollBack();
            }
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Run $callback on a second PDO session (new default connection) while the
     * current session still holds its open transaction.
     */
    public static function onPeer(callable $callback): mixed
    {
        $peer = self::peer();
        $original = (string) config('database.default');
        DB::setDefaultConnection(self::PEER);

        try {
            return $callback($peer);
        } finally {
            DB::setDefaultConnection($original);
        }
    }

    public static function isLockTimeout(QueryException $exception): bool
    {
        if ((string) $exception->getCode() === '55P03') {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'lock timeout')
            || str_contains($message, 'could not obtain lock')
            || str_contains($message, '55p03');
    }
}
