<?php

namespace App\Contracts\Accounting;

use App\Models\Accounting\AccountingLedger;
use App\Models\Accounting\CashRounding;
use App\Models\Accounting\Currency;
use App\Models\Accounting\FollowUpLevel;

interface AccountingConfigServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createCurrency(array $data): Currency;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCurrency(Currency $currency, array $data): Currency;

    public function deleteCurrency(Currency $currency): void;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCashRounding(array $data): CashRounding;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCashRounding(CashRounding $rounding, array $data): CashRounding;

    public function deleteCashRounding(CashRounding $rounding): void;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createLedger(array $data): AccountingLedger;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateLedger(AccountingLedger $ledger, array $data): AccountingLedger;

    public function deleteLedger(AccountingLedger $ledger): void;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFollowUpLevel(array $data): FollowUpLevel;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateFollowUpLevel(FollowUpLevel $level, array $data): FollowUpLevel;

    public function deleteFollowUpLevel(FollowUpLevel $level): void;
}
