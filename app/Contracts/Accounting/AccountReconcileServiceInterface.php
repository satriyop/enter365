<?php

namespace App\Contracts\Accounting;

use App\Models\Accounting\AccountReconciliation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AccountReconcileServiceInterface
{
    /**
     * Reconcilable accounts with outstanding residual totals.
     *
     * @return Collection<int, mixed>
     */
    public function accounts(): Collection;

    /**
     * Unreconciled posted journal lines for an account.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, mixed>
     */
    public function lines(array $filters): Collection;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function history(array $filters): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function reconcile(array $data): AccountReconciliation;

    public function unreconcile(AccountReconciliation $reconciliation): void;
}
