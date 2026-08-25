<?php

declare(strict_types=1);

use App\Models\Pos\PosSession;
use App\Services\Pos\PosService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\PostgresRowLock;

describe('POS session under a held row lock', function () {
    it('cannot close a till while another session holds the open row', function () {
        authenticatedAdmin();
        $session = PosSession::factory()->create();
        $service = app(PosService::class);

        DB::beginTransaction();

        try {
            PosSession::query()->lockForUpdate()->findOrFail($session->id);

            PostgresRowLock::onPeer(function () use ($service, $session): void {
                try {
                    $service->closeSession($session, [
                        'counted_cash_amount' => (int) $session->opening_cash_amount,
                    ]);
                    test()->fail('closeSession ran while the till row was locked by another session.');
                } catch (QueryException $exception) {
                    expect(PostgresRowLock::isLockTimeout($exception))->toBeTrue(
                        $exception->getMessage()
                    );
                }
            });
        } finally {
            DB::rollBack();
        }
    });
});
