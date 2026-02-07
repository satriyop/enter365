<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\FxRevaluationServiceInterface;
use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\DocumentStatus;
use App\Models\Accounting\JournalEntry;
use App\Services\Base\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FxRevaluationService extends BaseService implements FxRevaluationServiceInterface
{
    public function __construct(
        private JournalServiceInterface $journalService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    public function revalue(string $date, array $closingRates, bool $withReversal = false): Collection
    {
        return $this->executeInTransaction('fx_revaluation', function () use ($date, $closingRates, $withReversal) {
            $preview = $this->preview($date, $closingRates);
            $entries = collect();

            if ($preview['total_gain'] === 0 && $preview['total_loss'] === 0) {
                return $entries;
            }

            $lines = $this->buildRevaluationLines($preview);

            if (empty($lines)) {
                return $entries;
            }

            $entry = $this->journalService->createEntry([
                'entry_date' => $date,
                'description' => 'Revaluasi selisih kurs per '.$date,
                'reference' => 'FX-REVAL-'.$date,
                'source_type' => JournalEntry::SOURCE_FX_REVALUATION,
                'lines' => $lines,
            ], autoPost: true);

            $entries->push($entry);

            // Auto-reversal for next day
            if ($withReversal) {
                $reversalDate = Carbon::parse($date)->addDay()->toDateString();
                $reversal = $this->journalService->reverseEntry(
                    $entry,
                    'Pembalikan revaluasi selisih kurs '.$date
                );

                // Override the entry date to next day (reverseEntry uses original date)
                $reversal->update(['entry_date' => $reversalDate]);

                $entries->push($reversal);
            }

            return $entries;
        }, ['date' => $date, 'currencies' => array_keys($closingRates)]);
    }

    public function preview(string $date, array $closingRates): array
    {
        $items = [];
        $totalGain = 0;
        $totalLoss = 0;

        // Open statuses for invoices and bills
        $invoiceStatuses = [DocumentStatus::Sent, DocumentStatus::Partial, DocumentStatus::Overdue];
        $billStatuses = [DocumentStatus::Received, DocumentStatus::Partial, DocumentStatus::Overdue];

        foreach ($closingRates as $currency => $closingRate) {
            if ($currency === 'IDR') {
                continue;
            }

            // Revalue open AR (invoices)
            $openInvoices = DB::table('invoices')
                ->where('currency', $currency)
                ->whereIn('status', array_map(fn (DocumentStatus $s) => $s->value, $invoiceStatuses))
                ->whereNull('deleted_at')
                ->select(['id', 'invoice_number', 'total_amount', 'paid_amount', 'exchange_rate', 'currency'])
                ->get();

            foreach ($openInvoices as $inv) {
                $outstanding = $inv->total_amount - $inv->paid_amount;
                if ($outstanding <= 0) {
                    continue;
                }

                $bookedRate = (float) $inv->exchange_rate;
                $bookedBase = (int) round($outstanding * $bookedRate);
                $revaluedBase = (int) round($outstanding * $closingRate);
                $unrealizedFx = $revaluedBase - $bookedBase;

                if ($unrealizedFx === 0) {
                    continue;
                }

                $items[] = [
                    'type' => 'invoice',
                    'reference' => $inv->invoice_number,
                    'currency' => $currency,
                    'outstanding' => $outstanding,
                    'booked_rate' => $bookedRate,
                    'closing_rate' => $closingRate,
                    'booked_base' => $bookedBase,
                    'revalued_base' => $revaluedBase,
                    'unrealized_fx' => $unrealizedFx,
                ];

                if ($unrealizedFx > 0) {
                    $totalGain += $unrealizedFx;
                } else {
                    $totalLoss += abs($unrealizedFx);
                }
            }

            // Revalue open AP (bills)
            $openBills = DB::table('bills')
                ->where('currency', $currency)
                ->whereIn('status', array_map(fn (DocumentStatus $s) => $s->value, $billStatuses))
                ->whereNull('deleted_at')
                ->select(['id', 'bill_number', 'total_amount', 'paid_amount', 'exchange_rate', 'currency'])
                ->get();

            foreach ($openBills as $bill) {
                $outstanding = $bill->total_amount - $bill->paid_amount;
                if ($outstanding <= 0) {
                    continue;
                }

                $bookedRate = (float) $bill->exchange_rate;
                $bookedBase = (int) round($outstanding * $bookedRate);
                $revaluedBase = (int) round($outstanding * $closingRate);
                $unrealizedFx = $revaluedBase - $bookedBase;

                if ($unrealizedFx === 0) {
                    continue;
                }

                // For AP: rate increase = unrealized loss (we owe more IDR)
                // Flip sign: positive unrealizedFx on AP means LOSS, not gain
                $items[] = [
                    'type' => 'bill',
                    'reference' => $bill->bill_number,
                    'currency' => $currency,
                    'outstanding' => $outstanding,
                    'booked_rate' => $bookedRate,
                    'closing_rate' => $closingRate,
                    'booked_base' => $bookedBase,
                    'revalued_base' => $revaluedBase,
                    'unrealized_fx' => -$unrealizedFx, // flip: positive diff on AP = loss
                ];

                // AP sign is flipped: rate increase means we owe more = loss
                if ($unrealizedFx > 0) {
                    $totalLoss += $unrealizedFx;
                } else {
                    $totalGain += abs($unrealizedFx);
                }
            }
        }

        return [
            'items' => $items,
            'total_gain' => $totalGain,
            'total_loss' => $totalLoss,
        ];
    }

    /**
     * Build JE lines from preview results.
     *
     * Creates a single adjustment JE:
     * - For AR gain: Dr AR adjustment, Cr Unrealized FX Gain (4-2005)
     * - For AR loss: Dr Unrealized FX Loss (5-3007), Cr AR adjustment
     * - For AP: mirrored (rate increase on AP = loss)
     *
     * @return array<int, array{account_id: int, description: string, debit: int, credit: int}>
     */
    private function buildRevaluationLines(array $preview): array
    {
        $gainCode = config('accounting.default_accounts.unrealized_fx_gain'); // 4-2005
        $lossCode = config('accounting.default_accounts.unrealized_fx_loss'); // 5-3007
        $arCode = config('accounting.default_accounts.accounts_receivable');   // 1-1100
        $apCode = config('accounting.default_accounts.accounts_payable');      // 2-1100

        $lines = [];

        // Net AR adjustment (invoices)
        $arAdjustment = 0;
        foreach ($preview['items'] as $item) {
            if ($item['type'] === 'invoice') {
                // positive unrealized_fx = gain for AR
                $arAdjustment += $item['unrealized_fx'];
            }
        }

        // Net AP adjustment (bills) — already sign-flipped in preview
        $apAdjustment = 0;
        foreach ($preview['items'] as $item) {
            if ($item['type'] === 'bill') {
                // negative unrealized_fx = gain for AP (rate decreased, we owe less)
                // positive unrealized_fx = loss for AP (rate increased, we owe more)
                // Note: in preview, AP sign is already flipped, so:
                // positive = gain, negative = loss
                $apAdjustment += $item['unrealized_fx'];
            }
        }

        // AR revaluation lines
        if ($arAdjustment !== 0) {
            if ($arAdjustment > 0) {
                // AR went up → Dr AR, Cr Unrealized FX Gain
                $lines[] = [
                    'account_code' => $arCode,
                    'description' => 'Penyesuaian piutang revaluasi kurs',
                    'debit' => $arAdjustment,
                    'credit' => 0,
                ];
                $lines[] = [
                    'account_code' => $gainCode,
                    'description' => 'Keuntungan selisih kurs belum terealisasi',
                    'debit' => 0,
                    'credit' => $arAdjustment,
                ];
            } else {
                // AR went down → Dr Unrealized FX Loss, Cr AR
                $lines[] = [
                    'account_code' => $lossCode,
                    'description' => 'Kerugian selisih kurs belum terealisasi',
                    'debit' => abs($arAdjustment),
                    'credit' => 0,
                ];
                $lines[] = [
                    'account_code' => $arCode,
                    'description' => 'Penyesuaian piutang revaluasi kurs',
                    'debit' => 0,
                    'credit' => abs($arAdjustment),
                ];
            }
        }

        // AP revaluation lines (sign already flipped)
        if ($apAdjustment !== 0) {
            if ($apAdjustment > 0) {
                // AP decreased (gain) → Dr AP, Cr Unrealized FX Gain
                $lines[] = [
                    'account_code' => $apCode,
                    'description' => 'Penyesuaian utang revaluasi kurs',
                    'debit' => $apAdjustment,
                    'credit' => 0,
                ];
                $lines[] = [
                    'account_code' => $gainCode,
                    'description' => 'Keuntungan selisih kurs belum terealisasi',
                    'debit' => 0,
                    'credit' => $apAdjustment,
                ];
            } else {
                // AP increased (loss) → Dr Unrealized FX Loss, Cr AP
                $lines[] = [
                    'account_code' => $lossCode,
                    'description' => 'Kerugian selisih kurs belum terealisasi',
                    'debit' => abs($apAdjustment),
                    'credit' => 0,
                ];
                $lines[] = [
                    'account_code' => $apCode,
                    'description' => 'Penyesuaian utang revaluasi kurs',
                    'debit' => 0,
                    'credit' => abs($apAdjustment),
                ];
            }
        }

        return $lines;
    }
}
