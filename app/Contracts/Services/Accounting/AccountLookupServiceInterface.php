<?php

declare(strict_types=1);

namespace App\Contracts\Services\Accounting;

use App\Exceptions\Domain\MissingAccountException;
use App\Models\Accounting\Account;
use Illuminate\Support\Collection;

/**
 * Service for validated account lookups with caching.
 *
 * Provides fail-fast behavior when required accounts are missing,
 * preventing incomplete journal entries from being created.
 */
interface AccountLookupServiceInterface
{
    /**
     * Find an account by code or throw exception.
     *
     * @param  string  $code  The account code (e.g., '4-2001')
     * @param  string  $purpose  Description of why this account is needed
     *
     * @throws MissingAccountException When account doesn't exist
     */
    public function findByCodeOrFail(string $code, string $purpose = ''): Account;

    /**
     * Find multiple accounts by codes or throw exception.
     * Validates ALL codes exist before returning, listing all missing codes in exception.
     *
     * @param  array<string>  $codes  Account codes to find
     * @param  string  $operation  Description of the operation requiring these accounts
     * @return Collection<string, Account> Keyed by account code
     *
     * @throws MissingAccountException When any account doesn't exist (lists all missing)
     */
    public function findByCodesOrFail(array $codes, string $operation = ''): Collection;

    /**
     * Find an account by ID or throw exception.
     *
     * @throws MissingAccountException When account doesn't exist
     */
    public function findByIdOrFail(int $id, string $purpose = ''): Account;

    /**
     * Clear the internal cache.
     */
    public function clearCache(): void;
}
