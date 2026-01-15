<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domains;

use App\Contracts\Services\DocumentLifecycleInterface;
use App\Models\Purchasing\Bill;

/**
 * Interface for Bill service operations.
 *
 * Extends DocumentLifecycleInterface for CRUD operations and adds
 * bill-specific operations like posting to journal.
 */
interface BillServiceInterface extends DocumentLifecycleInterface
{
    /**
     * Post a bill to the journal (create accounting entry).
     *
     * @throws \InvalidArgumentException If bill is not in draft status
     */
    public function post(Bill $bill): Bill;
}
