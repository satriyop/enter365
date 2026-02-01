<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\AccountLookupServiceInterface;
use App\Exceptions\Domain\MissingAccountException;
use App\Models\Accounting\Account;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Validated account lookups with two-layer caching.
 *
 * Layer 1: Request-level in-memory cache (fastest, no serialization)
 * Layer 2: Persistent cache via Cache::forever (survives between requests)
 *
 * Cache is invalidated via Account model events (saved/deleted).
 */
class AccountLookupService implements AccountLookupServiceInterface
{
    private const CACHE_PREFIX = 'accounting:account:';

    /** @var array<string, Account> Request-level cache by code */
    private array $cacheByCode = [];

    /** @var array<int, Account> Request-level cache by ID */
    private array $cacheById = [];

    public function findByCodeOrFail(string $code, string $purpose = ''): Account
    {
        if (isset($this->cacheByCode[$code])) {
            return $this->cacheByCode[$code];
        }

        $account = $this->resolveByCode($code);

        if (! $account) {
            throw MissingAccountException::forCode($code, $purpose);
        }

        $this->populateRequestCache($account);

        return $account;
    }

    public function findByCodesOrFail(array $codes, string $operation = ''): Collection
    {
        $result = collect();
        $uncachedCodes = [];

        foreach ($codes as $code) {
            if (isset($this->cacheByCode[$code])) {
                $result[$code] = $this->cacheByCode[$code];
            } else {
                $attributes = Cache::get(self::CACHE_PREFIX."code:{$code}");

                if ($attributes !== null) {
                    $account = (new Account)->newFromBuilder($attributes);
                    $this->populateRequestCache($account);
                    $result[$code] = $account;
                } else {
                    $uncachedCodes[] = $code;
                }
            }
        }

        if (! empty($uncachedCodes)) {
            $accounts = Account::whereIn('code', $uncachedCodes)->get();

            foreach ($accounts as $account) {
                $this->populateRequestCache($account);
                $this->storePersistentCache($account);
                $result[$account->code] = $account;
            }
        }

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

        $account = $this->resolveById($id);

        if (! $account) {
            throw MissingAccountException::forId($id, $purpose);
        }

        $this->populateRequestCache($account);

        return $account;
    }

    public function clearCache(): void
    {
        $this->cacheByCode = [];
        $this->cacheById = [];
    }

    /**
     * Flush persistent cache for a specific account.
     * Called by Account model events on save/delete.
     */
    public static function flushPersistentCache(Account $account): void
    {
        Cache::forget(self::CACHE_PREFIX."code:{$account->code}");
        Cache::forget(self::CACHE_PREFIX."id:{$account->id}");

        if ($account->isDirty('code') && $account->getOriginal('code')) {
            Cache::forget(self::CACHE_PREFIX."code:{$account->getOriginal('code')}");
        }
    }

    public function getRetainedEarningsAccount(): Account
    {
        $code = config('accounting.default_accounts.retained_earnings', '3-2000');

        return $this->findByCodeOrFail($code, 'Laba Ditahan');
    }

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

    public function getIncomeSummaryAccount(): Account
    {
        $code = config('accounting.default_accounts.income_summary', '3-9000');

        return $this->findByCodeOrFail($code, 'Ikhtisar Laba/Rugi');
    }

    private function resolveByCode(string $code): ?Account
    {
        $attributes = Cache::get(self::CACHE_PREFIX."code:{$code}");

        if ($attributes !== null) {
            return (new Account)->newFromBuilder($attributes);
        }

        $account = Account::where('code', $code)->first();

        if ($account) {
            $this->storePersistentCache($account);
        }

        return $account;
    }

    private function resolveById(int $id): ?Account
    {
        $attributes = Cache::get(self::CACHE_PREFIX."id:{$id}");

        if ($attributes !== null) {
            return (new Account)->newFromBuilder($attributes);
        }

        $account = Account::find($id);

        if ($account) {
            $this->storePersistentCache($account);
        }

        return $account;
    }

    private function populateRequestCache(Account $account): void
    {
        $this->cacheByCode[$account->code] = $account;
        $this->cacheById[$account->id] = $account;
    }

    private function storePersistentCache(Account $account): void
    {
        Cache::forever(self::CACHE_PREFIX."code:{$account->code}", $account->getAttributes());
        Cache::forever(self::CACHE_PREFIX."id:{$account->id}", $account->getAttributes());
    }
}
