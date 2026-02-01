<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\Accounting\Account;

/**
 * Interface for Chart of Accounts service operations.
 */
interface AccountServiceInterface
{
    /**
     * Create a new account.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Account;

    /**
     * Update an existing account.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Account $account, array $data): Account;

    /**
     * Delete an account.
     */
    public function delete(Account $account): void;
}
