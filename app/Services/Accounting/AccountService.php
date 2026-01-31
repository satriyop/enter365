<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Models\Accounting\Account;
use App\Services\Base\BaseService;

class AccountService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new account.
     *
     * @param  array{code: string, name: string, type: string, parent_id?: int, description?: string, is_active?: bool}  $data
     */
    public function create(array $data): Account
    {
        return $this->executeInTransaction('create_account', function () use ($data) {
            return Account::create($data);
        }, ['code' => $data['code']]);
    }

    /**
     * Update an account.
     *
     * @param  array{code?: string, name?: string, type?: string, parent_id?: int, description?: string, is_active?: bool}  $data
     */
    public function update(Account $account, array $data): Account
    {
        return $this->executeInTransaction('update_account', function () use ($account, $data) {
            if ($account->is_system && isset($data['code']) && $data['code'] !== $account->code) {
                throw new \Exception('Tidak bisa mengubah kode akun sistem.');
            }

            $account->update($data);

            return $account->fresh(['parent', 'children']);
        }, ['account_id' => $account->id]);
    }

    /**
     * Delete an account.
     */
    public function delete(Account $account): void
    {
        $this->executeInTransaction('delete_account', function () use ($account) {
            if ($account->is_system) {
                throw new \Exception('Tidak bisa menghapus akun sistem.');
            }

            if ($account->journalEntryLines()->exists()) {
                throw new \Exception('Tidak bisa menghapus akun yang sudah digunakan dalam jurnal.');
            }

            $account->delete();
        }, ['account_id' => $account->id]);
    }
}
