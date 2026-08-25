<?php

declare(strict_types=1);

use App\Models\Sales\Invoice;
use App\Services\Sales\InvoicePaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\PostgresRowLock;

describe('Invoice payment under a held row lock', function () {
    it('cannot record a payment while another session holds the invoice', function () {
        authenticatedAdmin();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);

        $invoice = createSentInvoice();
        $invoice->forceFill([
            'total_amount' => 1_000_000,
            'paid_amount' => 0,
        ])->save();

        $service = app(InvoicePaymentService::class);

        DB::beginTransaction();

        try {
            Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            PostgresRowLock::onPeer(function () use ($service, $invoice): void {
                try {
                    $service->recordPayment($invoice, 100_000);
                    test()->fail('recordPayment completed while the invoice row was locked by another session.');
                } catch (QueryException $exception) {
                    expect(PostgresRowLock::isLockTimeout($exception))->toBeTrue(
                        $exception->getMessage()
                    );
                }
            });
        } finally {
            DB::rollBack();
        }

        $result = $service->recordPayment($invoice, 100_000);
        expect((int) $result->paid_amount)->toBe(100_000);
    });
});
