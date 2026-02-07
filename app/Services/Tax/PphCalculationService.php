<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Contracts\Tax\PphCalculationServiceInterface;
use App\Domain\Tax\ValueObjects\PphCalculationResult;
use App\Enums\PphCategory;
use App\Models\Purchasing\Bill;

class PphCalculationService implements PphCalculationServiceInterface
{
    public function calculateForBillPayment(
        Bill $bill,
        int $paymentAmount,
        ?PphCategory $categoryOverride = null,
        ?float $rateOverride = null
    ): ?PphCalculationResult {
        if (! config('accounting.pph.enabled')) {
            return null;
        }

        $contact = $bill->contact;
        if (! $contact || ! $contact->isPphSubject()) {
            return null;
        }

        // Determine category: override → bill → contact default
        $category = $categoryOverride ?? $bill->pph_category ?? $contact->pph_category;
        if (! $category) {
            return null;
        }

        // Determine rate: override → bill → contact effective rate → config default
        $noNpwpSurcharge = false;
        if ($rateOverride !== null) {
            $rate = $rateOverride;
        } elseif ($bill->pph_rate !== null && (float) $bill->pph_rate > 0) {
            $rate = (float) $bill->pph_rate;
        } else {
            $rate = $contact->getEffectivePphRate();
            // Check if surcharge was applied
            $baseRate = $category->effectiveRate();
            if ($rate > $baseRate) {
                $noNpwpSurcharge = true;
            }
        }

        if ($rate <= 0) {
            return null;
        }

        // Calculate proportional PPh for this payment
        // If bill has pph_amount set, prorate: pphForPayment = bill.pph_amount * (payment / total)
        // Then cap at remaining withholding
        $billTotal = $bill->total_amount;
        if ($billTotal <= 0) {
            return null;
        }

        $pphAmount = 0;
        if ($bill->pph_amount > 0) {
            // Proportional calculation
            $pphAmount = (int) round($bill->pph_amount * ($paymentAmount / $billTotal));
            // Cap at remaining
            $remaining = $bill->getRemainingPphWithholding();
            $pphAmount = min($pphAmount, $remaining);
        } else {
            // Calculate from scratch (bill may not have PPh pre-calculated)
            $dpp = $bill->subtotal - $bill->discount_amount;
            $totalPph = (int) round($dpp * $rate / 100);
            $pphAmount = (int) round($totalPph * ($paymentAmount / $billTotal));
        }

        if ($pphAmount <= 0) {
            return null;
        }

        $cashAmount = $paymentAmount - $pphAmount;
        $accountCode = $category->accountCode();

        return new PphCalculationResult(
            category: $category,
            rate: $rate,
            baseAmount: $bill->pph_base_amount > 0 ? $bill->pph_base_amount : ($bill->subtotal - $bill->discount_amount),
            pphAmount: $pphAmount,
            cashAmount: $cashAmount,
            accountCode: $accountCode,
            noNpwpSurcharge: $noNpwpSurcharge
        );
    }
}
