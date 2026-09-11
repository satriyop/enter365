<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\AccountingConfigServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\AccountingLedger;
use App\Models\Accounting\CashRounding;
use App\Models\Accounting\Currency;
use App\Services\Base\BaseService;
use Illuminate\Support\Arr;

class AccountingConfigService extends BaseService implements AccountingConfigServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCurrency(array $data): Currency
    {
        return $this->executeInTransaction('create_currency', function () use ($data) {
            $payload = $this->currencyPayload($data);
            $currency = Currency::query()->create($payload);
            if ($currency->is_base_currency) {
                $this->ensureSingleBase($currency);
            }

            return $currency->fresh() ?? $currency;
        }, ['code' => $data['code'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCurrency(Currency $currency, array $data): Currency
    {
        return $this->executeInTransaction('update_currency', function () use ($currency, $data) {
            $currency->update($this->currencyPayload($data, $currency));
            $currency = $currency->fresh() ?? $currency;
            if ($currency->is_base_currency) {
                $this->ensureSingleBase($currency);
            }

            return $currency;
        }, ['currency_id' => $currency->id]);
    }

    public function deleteCurrency(Currency $currency): void
    {
        $this->executeInTransaction('delete_currency', function () use ($currency) {
            if ($currency->is_base_currency) {
                throw new BusinessRuleException('Mata uang dasar tidak bisa dihapus.');
            }
            $currency->delete();
        }, ['currency_id' => $currency->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCashRounding(array $data): CashRounding
    {
        return $this->executeInTransaction('create_cash_rounding', function () use ($data) {
            return CashRounding::query()->create($this->roundingPayload($data));
        }, ['name' => $data['name'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCashRounding(CashRounding $rounding, array $data): CashRounding
    {
        return $this->executeInTransaction('update_cash_rounding', function () use ($rounding, $data) {
            $rounding->update($this->roundingPayload($data));

            return $rounding->fresh(['profitAccount', 'lossAccount']) ?? $rounding;
        }, ['cash_rounding_id' => $rounding->id]);
    }

    public function deleteCashRounding(CashRounding $rounding): void
    {
        $this->executeInTransaction('delete_cash_rounding', function () use ($rounding) {
            $rounding->delete();
        }, ['cash_rounding_id' => $rounding->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createLedger(array $data): AccountingLedger
    {
        return $this->executeInTransaction('create_accounting_ledger', function () use ($data) {
            $ledger = AccountingLedger::query()->create($this->ledgerPayload($data));
            if ($ledger->is_default) {
                $this->ensureSingleDefaultLedger($ledger);
            }

            return $ledger->fresh() ?? $ledger;
        }, ['code' => $data['code'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateLedger(AccountingLedger $ledger, array $data): AccountingLedger
    {
        return $this->executeInTransaction('update_accounting_ledger', function () use ($ledger, $data) {
            $ledger->update($this->ledgerPayload($data));
            $ledger = $ledger->fresh() ?? $ledger;
            if ($ledger->is_default) {
                $this->ensureSingleDefaultLedger($ledger);
            }

            return $ledger;
        }, ['accounting_ledger_id' => $ledger->id]);
    }

    public function deleteLedger(AccountingLedger $ledger): void
    {
        $this->executeInTransaction('delete_accounting_ledger', function () use ($ledger) {
            if ($ledger->is_default) {
                throw new BusinessRuleException('Buku besar default tidak bisa dihapus.');
            }
            $ledger->delete();
        }, ['accounting_ledger_id' => $ledger->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function currencyPayload(array $data, ?Currency $existing = null): array
    {
        $payload = Arr::only($data, ['code', 'name', 'symbol', 'decimal_places', 'is_base_currency', 'is_active']);
        if (isset($payload['code'])) {
            $payload['code'] = strtoupper((string) $payload['code']);
        }
        if (($payload['is_base_currency'] ?? false) === false && $existing?->is_base_currency) {
            $others = Currency::query()->where('id', '!=', $existing->id)->where('is_base_currency', true)->exists();
            if (! $others) {
                throw new BusinessRuleException('Harus ada satu mata uang dasar.');
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function roundingPayload(array $data): array
    {
        $payload = Arr::only($data, [
            'name', 'rounding', 'strategy', 'profit_account_id', 'loss_account_id', 'is_active', 'notes',
        ]);
        if (isset($payload['rounding']) && (int) $payload['rounding'] < 1) {
            throw new BusinessRuleException('Nilai pembulatan harus lebih dari 0.');
        }
        if (isset($payload['strategy']) && ! in_array($payload['strategy'], CashRounding::strategies(), true)) {
            throw new BusinessRuleException('Metode pembulatan tidak valid.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function ledgerPayload(array $data): array
    {
        $payload = Arr::only($data, ['code', 'name', 'currency_code', 'is_default', 'is_active', 'notes']);
        if (isset($payload['code'])) {
            $payload['code'] = strtoupper((string) $payload['code']);
        }

        return $payload;
    }

    private function ensureSingleBase(Currency $currency): void
    {
        Currency::query()->where('id', '!=', $currency->id)->where('is_base_currency', true)->update([
            'is_base_currency' => false,
        ]);
    }

    private function ensureSingleDefaultLedger(AccountingLedger $ledger): void
    {
        AccountingLedger::query()->where('id', '!=', $ledger->id)->where('is_default', true)->update([
            'is_default' => false,
        ]);
    }
}
