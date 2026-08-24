<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Contracts\Accounting\AccountLookupServiceInterface;
use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Inventory\InventoryServiceInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Pos\PosServiceInterface;
use App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus;
use App\Domain\Accounting\Tax\TaxInclusiveStrategy;
use App\Domain\Pos\PosAddOnBill;
use App\Domain\Shared\DocumentNumbers;
use App\Domain\Shared\ValueObjects\Money;
use App\Enums\Pos\PosPricingMode;
use App\Enums\Pos\PosSaleStatus;
use App\Enums\Pos\PosSessionStatus;
use App\Enums\Pos\PosTenderType;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosCheckoutIdempotency;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSession;
use App\Models\Pos\PosSessionHold;
use App\Services\Base\BaseService;
use Illuminate\Support\Collection;

class PosService extends BaseService implements PosServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private AccountLookupServiceInterface $accountLookup,
        private JournalServiceInterface $journalService,
        private InventoryServiceInterface $inventoryService,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    public function openSession(array $data): PosSession
    {
        return $this->executeInTransaction('pos_open_session', function () use ($data) {
            $userId = $this->getUserId();
            if ($userId !== null) {
                $existing = $this->currentOpenSession($userId);
                if ($existing !== null) {
                    return $existing->load('warehouse');
                }
            }

            $this->assertPeriodAllowsTill(now());

            $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);
            $cash = $this->accountLookup->findByCodeOrFail(
                (string) config('accounting.default_accounts.cash'),
                'kas POS'
            );
            $qris = $this->accountLookup->findByCodeOrFail(
                (string) config('accounting.default_accounts.qris'),
                'QRIS POS'
            );

            return PosSession::query()->create([
                'session_number' => DocumentNumbers::generate('PSS-'.now()->format('Ym').'-', 'pos_sessions', 'session_number'),
                'status' => PosSessionStatus::Open,
                'warehouse_id' => $warehouse->id,
                'cash_account_id' => $cash->id,
                'qris_account_id' => $qris->id,
                'pricing_mode' => PosPricingMode::from((string) config('pos.pricing_mode', PosPricingMode::Inclusive->value)),
                'service_rate' => (float) config('pos.service_rate', 0),
                'tax_add_rate' => (float) config('pos.tax_rate', 0),
                'tax_add_name' => (string) config('pos.tax_name', 'PBJT'),
                'opening_cash_amount' => (int) $data['opening_cash_amount'],
                'opened_by' => $this->getUserId(),
                'opened_at' => now(),
            ]);
        });
    }

    public function closeSession(PosSession $session, array $data): PosSession
    {
        return $this->executeInTransaction('pos_close_session', function () use ($session, $data) {
            $session = PosSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->assertOpen($session);
            $this->assertPeriodAllowsTill(now());

            $expected = $this->expectedCash($session);
            $counted = (int) $data['counted_cash_amount'];
            $difference = $counted - $expected;

            $session->update([
                'status' => PosSessionStatus::Closed,
                'expected_cash_amount' => $expected,
                'counted_cash_amount' => $counted,
                'cash_difference_amount' => $difference,
                'closed_by' => $this->getUserId(),
                'closed_at' => now(),
            ]);

            $session->holds()->delete();

            if ($difference !== 0) {
                $this->postCashOverShort($session->fresh(), $difference);
            }

            return $session->fresh();
        }, ['pos_session_id' => $session->id]);
    }

    public function checkout(PosSession $session, array $data, string $idempotencyKey): PosSale
    {
        if ($idempotencyKey === '') {
            throw BusinessRuleException::operationNotAllowed('checkout', 'Idempotency-Key wajib diisi.');
        }

        return $this->executeInTransaction('pos_checkout', function () use ($session, $data, $idempotencyKey) {
            $session = PosSession::query()->lockForUpdate()->findOrFail($session->id);
            $session->load('warehouse');
            $this->assertOpen($session);
            $this->assertPeriodAllowsTill(now());
            $warehouse = $session->warehouse;
            if ($warehouse === null) {
                throw BusinessRuleException::operationNotAllowed('checkout', 'Gudang sesi kasir tidak ditemukan.');
            }

            $existing = PosCheckoutIdempotency::query()
                ->where('pos_session_id', $session->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return PosSale::query()->with(['items', 'tenders'])->findOrFail($existing->pos_sale_id);
            }

            $way = $data['way'] ?? '';
            $lines = $data['lines'] ?? [];
            if ($lines === []) {
                throw BusinessRuleException::operationNotAllowed('checkout', 'Pesanan kosong.');
            }

            $built = $this->buildLines($lines, $session);
            $linePayable = (int) array_sum(array_column($built, 'payable_amount'));
            $dpp = (int) array_sum(array_column($built, 'dpp_amount'));
            $ppn = (int) array_sum(array_column($built, 'ppn_amount'));
            $serviceAmount = 0;
            $taxAmount = 0;
            $subtotal = $linePayable;
            $payable = $linePayable;

            if ($session->usesAddOnPricing()) {
                $bill = PosAddOnBill::of($linePayable, (float) $session->service_rate, (float) $session->tax_add_rate);
                $subtotal = $bill->subtotal;
                $serviceAmount = $bill->serviceAmount;
                $taxAmount = $bill->taxAmount;
                $payable = $bill->payable;
                $dpp = $subtotal;
                $ppn = 0;
            }

            $cashReceived = 0;
            $change = 0;
            $tenderType = PosTenderType::Cash;

            if ($way === PosTenderType::Qris->value) {
                $tenderType = PosTenderType::Qris;
            } elseif ($way === PosTenderType::Cash->value) {
                $cashReceived = (int) ($data['cash_received_amount'] ?? 0);
                if ($cashReceived < $payable) {
                    throw BusinessRuleException::operationNotAllowed('checkout', 'Uang tunai kurang.');
                }
                $change = $cashReceived - $payable;
            } else {
                throw BusinessRuleException::operationNotAllowed('checkout', 'Cara bayar harus tunai atau QRIS.');
            }

            $sale = PosSale::query()->create([
                'sale_number' => DocumentNumbers::generate('POS-'.now()->format('Ym').'-', 'pos_sales', 'sale_number'),
                'pos_session_id' => $session->id,
                'status' => PosSaleStatus::Completed,
                'subtotal_amount' => $subtotal,
                'service_amount' => $serviceAmount,
                'tax_amount' => $taxAmount,
                'dpp_amount' => $dpp,
                'ppn_amount' => $ppn,
                'payable_amount' => $payable,
                'cash_received_amount' => $cashReceived,
                'change_amount' => $change,
                'sold_at' => now(),
                'created_by' => $this->getUserId(),
            ]);

            $cogsTotal = 0;
            foreach ($built as $row) {
                $movementId = null;
                $cogsAmount = 0;
                if ($row['track_inventory']) {
                    $movement = $this->inventoryService->stockOut(
                        $row['product'],
                        $warehouse,
                        $row['quantity'],
                        'POS '.$sale->sale_number,
                        PosSale::class,
                        $sale->id,
                    );
                    $movementId = $movement->id;
                    $cogsAmount = abs((int) $movement->total_cost);
                    $cogsTotal += $cogsAmount;
                }

                $sale->items()->create([
                    'product_id' => $row['product']->id,
                    'quantity' => $row['quantity'],
                    'unit_price_inclusive' => $row['unit_price_inclusive'],
                    'payable_amount' => $row['payable_amount'],
                    'dpp_amount' => $row['dpp_amount'],
                    'ppn_amount' => $row['ppn_amount'],
                    'is_taxable' => $row['is_taxable'],
                    'track_inventory' => $row['track_inventory'],
                    'inventory_movement_id' => $movementId,
                    'cogs_amount' => $cogsAmount,
                ]);
            }

            $sale->tenders()->create([
                'type' => $tenderType,
                'amount' => $payable,
            ]);

            $cashAccountId = $tenderType === PosTenderType::Cash
                ? $session->cash_account_id
                : $session->qris_account_id;

            $revenueLines = $this->revenueJournalLines(
                $sale,
                $cashAccountId,
                $payable,
                $dpp,
                $ppn,
                $serviceAmount,
                $taxAmount,
                $session,
            );

            $saleJe = $this->journalService->createEntry([
                'entry_date' => now()->toDateString(),
                'description' => 'Penjualan kasir '.$sale->sale_number,
                'reference' => $sale->sale_number,
                'source_type' => PosSale::class,
                'source_id' => $sale->id,
                'lines' => $revenueLines,
            ], autoPost: true);

            $cogsJeId = null;
            if ($cogsTotal > 0) {
                $cogsJe = $this->journalService->createEntry([
                    'entry_date' => now()->toDateString(),
                    'description' => 'HPP kasir '.$sale->sale_number,
                    'reference' => $sale->sale_number,
                    'source_type' => PosSale::class,
                    'source_id' => $sale->id,
                    'lines' => [
                        [
                            'account_code' => config('accounting.default_accounts.cogs'),
                            'debit' => $cogsTotal,
                            'credit' => 0,
                            'description' => 'HPP '.$sale->sale_number,
                        ],
                        [
                            'account_code' => config('accounting.default_accounts.inventory'),
                            'debit' => 0,
                            'credit' => $cogsTotal,
                            'description' => 'Persediaan '.$sale->sale_number,
                        ],
                    ],
                ], autoPost: true);
                $cogsJeId = $cogsJe->id;
            }

            $sale->update([
                'journal_entry_id' => $saleJe->id,
                'cogs_journal_entry_id' => $cogsJeId,
            ]);

            PosCheckoutIdempotency::query()->create([
                'pos_session_id' => $session->id,
                'idempotency_key' => $idempotencyKey,
                'pos_sale_id' => $sale->id,
            ]);

            return $sale->fresh(['items', 'tenders']);
        }, ['pos_session_id' => $session->id]);
    }

    public function voidSale(PosSession $session, PosSale $sale, string $reason): PosSale
    {
        return $this->executeInTransaction('pos_void', function () use ($session, $sale, $reason) {
            $session = PosSession::query()->lockForUpdate()->findOrFail($session->id);
            $session->load('warehouse');
            $this->assertOpen($session);
            $warehouse = $session->warehouse;
            if ($warehouse === null) {
                throw BusinessRuleException::operationNotAllowed('void', 'Gudang sesi kasir tidak ditemukan.');
            }

            $sale = PosSale::query()->lockForUpdate()->with('items.inventoryMovement', 'items.product')->findOrFail($sale->id);
            $this->assertPeriodAllowsTill(now());
            if ($sale->pos_session_id !== $session->id) {
                throw BusinessRuleException::operationNotAllowed('void', 'Penjualan bukan milik sesi ini.');
            }
            if (! $sale->isCompleted()) {
                throw BusinessRuleException::operationNotAllowed('void', 'Hanya penjualan selesai yang bisa dibatalkan.');
            }

            foreach ($sale->items as $item) {
                if ($item->track_inventory && $item->inventoryMovement) {
                    $this->inventoryService->stockIn(
                        $item->product,
                        $warehouse,
                        $item->quantity,
                        (int) $item->inventoryMovement->unit_cost,
                        'Void '.$sale->sale_number,
                        PosSale::class,
                        $sale->id,
                    );
                }
            }

            $saleJournal = $sale->journalEntry;
            if ($saleJournal) {
                $this->journalService->reverseEntry($saleJournal, 'Void '.$sale->sale_number);
            }
            $cogsJournal = $sale->cogsJournalEntry;
            if ($cogsJournal) {
                $this->journalService->reverseEntry($cogsJournal, 'Void HPP '.$sale->sale_number);
            }

            $sale->update([
                'status' => PosSaleStatus::Voided,
                'voided_at' => now(),
                'voided_by' => $this->getUserId(),
                'void_reason' => $reason,
            ]);

            return $sale->fresh(['items', 'tenders']);
        }, ['pos_sale_id' => $sale->id]);
    }

    public function hold(PosSession $session, array $lines): PosSessionHold
    {
        return $this->executeInTransaction('pos_hold', function () use ($session, $lines) {
            $session = PosSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->assertOpen($session);
            if ($lines === []) {
                throw BusinessRuleException::operationNotAllowed('simpan', 'Pesanan kosong.');
            }
            if ($session->holds()->count() >= 5) {
                throw BusinessRuleException::operationNotAllowed(
                    'simpan',
                    'Maksimal 5 pesanan ditahan — ambil atau kosongkan dulu.'
                );
            }

            foreach ($lines as $line) {
                Product::query()->findOrFail($line['product_id']);
            }

            /** @var PosSessionHold $hold */
            $hold = $session->holds()->create([
                'lines' => array_values(array_map(fn (array $line) => [
                    'product_id' => (int) $line['product_id'],
                    'quantity' => (int) $line['quantity'],
                ], $lines)),
            ]);

            return $hold;
        });
    }

    public function listHolds(PosSession $session): Collection
    {
        /** @var Collection<int, PosSessionHold> $holds */
        $holds = $session->holds()->latest()->get();

        return $holds;
    }

    public function takeHold(PosSession $session, PosSessionHold $hold): PosSessionHold
    {
        return $this->executeInTransaction('pos_take_hold', function () use ($session, $hold) {
            $this->assertOpen($session);
            $hold = PosSessionHold::query()->lockForUpdate()->findOrFail($hold->id);
            if ($hold->pos_session_id !== $session->id) {
                throw BusinessRuleException::operationNotAllowed('ambil', 'Pesanan ditahan bukan milik sesi ini.');
            }

            $copy = $hold->replicate();
            $hold->delete();

            return $copy;
        });
    }

    public function currentOpenSession(int $userId): ?PosSession
    {
        return PosSession::query()
            ->where('opened_by', $userId)
            ->where('status', PosSessionStatus::Open)
            ->latest('opened_at')
            ->first();
    }

    public function expectedCash(PosSession $session): int
    {
        $cashTenders = $session->sales()
            ->where('status', PosSaleStatus::Completed)
            ->with('tenders')
            ->get()
            ->sum(function (PosSale $sale) {
                return $sale->tenders
                    ->where('type', PosTenderType::Cash)
                    ->sum('amount');
            });

        return (int) $session->opening_cash_amount + (int) $cashTenders;
    }

    private function assertOpen(PosSession $session): void
    {
        if (! $session->isOpen()) {
            throw BusinessRuleException::operationNotAllowed('sesi kasir', 'Sesi kasir sudah ditutup.');
        }
    }

    /**
     * Book the till count vs expected cash. Sales already debited Kas for the
     * expected amount; without this entry the GL cash balance is a fiction.
     */
    private function postCashOverShort(PosSession $session, int $difference): void
    {
        $amount = abs($difference);
        $overShortCode = (string) config('accounting.default_accounts.cash_over_short');
        $cashAccountId = (int) $session->cash_account_id;
        $label = $difference < 0 ? 'selisih kurang' : 'selisih lebih';

        $overShortLine = [
            'account_code' => $overShortCode,
            'debit' => $difference < 0 ? $amount : 0,
            'credit' => $difference > 0 ? $amount : 0,
            'description' => "Selisih kas sesi {$session->session_number} ({$label})",
        ];
        $cashLine = [
            'account_id' => $cashAccountId,
            'debit' => $difference > 0 ? $amount : 0,
            'credit' => $difference < 0 ? $amount : 0,
            'description' => "Selisih kas sesi {$session->session_number} ({$label})",
        ];

        $this->journalService->createEntry([
            'entry_date' => now()->toDateString(),
            'description' => "Selisih kas kasir {$session->session_number}",
            'reference' => $session->session_number,
            'source_type' => PosSession::class,
            'source_id' => $session->id,
            'lines' => $difference < 0
                ? [$overShortLine, $cashLine]
                : [$cashLine, $overShortLine],
        ], autoPost: true);
    }

    private function assertPeriodAllowsTill(\DateTimeInterface $date): void
    {
        $period = FiscalPeriod::forDate($date);
        if ($period === null) {
            throw BusinessRuleException::operationNotAllowed(
                'periode fiskal',
                'Tidak ada periode fiskal untuk tanggal ini.'
            );
        }
        if ($period->getStatus() === FiscalPeriodStatus::Closed) {
            throw BusinessRuleException::fiscalPeriodClosed($period->name);
        }
        if ($period->getStatus() === FiscalPeriodStatus::Locked) {
            throw BusinessRuleException::operationNotAllowed(
                'periode fiskal',
                "Periode fiskal '{$period->name}' sedang dikunci."
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function revenueJournalLines(
        PosSale $sale,
        int $cashAccountId,
        int $payable,
        int $dpp,
        int $ppn,
        int $serviceAmount,
        int $taxAmount,
        PosSession $session,
    ): array {
        $number = $sale->sale_number;
        $lines = [
            [
                'account_id' => $cashAccountId,
                'debit' => $payable,
                'credit' => 0,
                'description' => 'POS '.$number,
            ],
            [
                'account_code' => config('accounting.default_accounts.sales_revenue'),
                'debit' => 0,
                'credit' => $dpp,
                'description' => 'Pendapatan '.$number,
            ],
        ];

        if ($session->usesAddOnPricing()) {
            if ($serviceAmount > 0) {
                $lines[] = [
                    'account_code' => config('accounting.default_accounts.service_charge'),
                    'debit' => 0,
                    'credit' => $serviceAmount,
                    'description' => 'Service '.$number,
                ];
            }
            if ($taxAmount > 0) {
                $lines[] = [
                    'account_code' => config('accounting.default_accounts.pbjt_payable'),
                    'debit' => 0,
                    'credit' => $taxAmount,
                    'description' => ($session->tax_add_name ?: 'PBJT').' '.$number,
                ];
            }

            return $lines;
        }

        if ($ppn > 0) {
            $lines[] = [
                'account_code' => config('accounting.default_accounts.tax_payable'),
                'debit' => 0,
                'credit' => $ppn,
                'description' => 'PPN '.$number,
            ];
        } else {
            $lines[1]['credit'] = $payable;
        }

        return $lines;
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines
     * @return list<array<string, mixed>>
     */
    private function buildLines(array $lines, PosSession $session): array
    {
        $built = [];
        $addOn = $session->usesAddOnPricing();
        foreach ($lines as $line) {
            $product = Product::query()->findOrFail($line['product_id']);
            $qty = (int) $line['quantity'];
            if ($qty < 1) {
                throw BusinessRuleException::operationNotAllowed('checkout', 'Jumlah harus lebih dari 0.');
            }
            if (! $product->is_active || ! $product->is_sellable) {
                throw BusinessRuleException::operationNotAllowed(
                    'checkout',
                    "{$product->name} tidak bisa dijual."
                );
            }
            $unit = (! $addOn && $product->is_taxable)
                ? (int) $product->selling_price_with_tax
                : (int) $product->selling_price;
            $payable = $unit * $qty;
            $dpp = $payable;
            $ppn = 0;
            if (! $addOn && $product->is_taxable) {
                $inclusive = new TaxInclusiveStrategy;
                $total = Money::of($payable);
                $rate = (float) $product->tax_rate;
                $dpp = $inclusive->getBaseAmount($total, $rate)->amount;
                $ppn = $inclusive->calculate($total, $rate)->amount;
            }

            $built[] = [
                'product' => $product,
                'quantity' => $qty,
                'unit_price_inclusive' => $unit,
                'payable_amount' => $payable,
                'dpp_amount' => $dpp,
                'ppn_amount' => $ppn,
                'is_taxable' => $addOn ? false : (bool) $product->is_taxable,
                'track_inventory' => (bool) $product->track_inventory,
            ];
        }

        return $built;
    }
}
