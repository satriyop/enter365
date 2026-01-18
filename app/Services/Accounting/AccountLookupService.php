<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\AccountLookupServiceInterface;
use App\Exceptions\Domain\MissingAccountException;
use App\Models\Accounting\Account;
use Illuminate\Support\Collection;

/**
 * Validated account lookups with request-level caching.
 *
 * Provides fail-fast behavior to prevent incomplete journal entries.
 * Caches lookups within the same request to avoid repeated queries.
 */
class AccountLookupService implements AccountLookupServiceInterface
{
    /** @var array<string, Account> Request-level cache by code */
    private array $cacheByCode = [];

    /** @var array<int, Account> Request-level cache by ID */
    private array $cacheById = [];

    public function findByCodeOrFail(string $code, string $purpose = ''): Account
    {
        if (isset($this->cacheByCode[$code])) {
            return $this->cacheByCode[$code];
        }

        $account = Account::where('code', $code)->first();

        if (! $account) {
            throw MissingAccountException::forCode($code, $purpose);
        }

        $this->cacheByCode[$code] = $account;
        $this->cacheById[$account->id] = $account;

        return $account;
    }

    public function findByCodesOrFail(array $codes, string $operation = ''): Collection
    {
        $result = collect();
        $uncachedCodes = [];

        // Check cache first
        foreach ($codes as $code) {
            if (isset($this->cacheByCode[$code])) {
                $result[$code] = $this->cacheByCode[$code];
            } else {
                $uncachedCodes[] = $code;
            }
        }

        // Batch fetch uncached
        if (! empty($uncachedCodes)) {
            $accounts = Account::whereIn('code', $uncachedCodes)->get();

            foreach ($accounts as $account) {
                $this->cacheByCode[$account->code] = $account;
                $this->cacheById[$account->id] = $account;
                $result[$account->code] = $account;
            }
        }

        // Check for missing codes (fail-fast with ALL missing codes)
        $foundCodes = $result->keys()->toArray();
        $missingCodes = array_diff($codes, $foundCodes);

        if (! empty($missingCodes)) {
            throw MissingAccountException::forCodes(array_values($missingCodes), $operation);
        }

        return $result;
    }

    public function findByIdOrFail(int $id, string $purpose = ''): Account
    {
        if (isset($this->cacheById[$id])) {
            return $this->cacheById[$id];
        }

        $account = Account::find($id);

        if (! $account) {
            throw MissingAccountException::forId($id, $purpose);
        }

        $this->cacheById[$id] = $account;
        $this->cacheByCode[$account->code] = $account;

        return $account;
    }

    public function clearCache(): void
    {
        $this->cacheByCode = [];
        $this->cacheById = [];
    }

    /**
     * Get the retained earnings account.
     */
    public function getRetainedEarningsAccount(): Account
    {
        $code = config('accounting.default_accounts.retained_earnings', '3-2000');

        return $this->findByCodeOrFail($code, 'Laba Ditahan');
    }

    /**
     * Get the dividends/withdrawals account (optional).
     */
    public function getDividendsAccount(): ?Account
    {
        $code = config('accounting.default_accounts.dividends');

        if (! $code) {
            return null;
        }

        try {
            return $this->findByCodeOrFail($code, 'Dividen/Prive');
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Get the income summary account (for closing).
     */
    public function getIncomeSummaryAccount(): Account
    {
        $code = config('accounting.default_accounts.income_summary', '3-9000');

        return $this->findByCodeOrFail($code, 'Ikhtisar Laba/Rugi');
    }
}
