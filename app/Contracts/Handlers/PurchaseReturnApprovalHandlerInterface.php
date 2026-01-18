<?php

declare(strict_types=1);

namespace App\Contracts\Handlers;

use App\Models\Purchasing\PurchaseReturn;

/**
 * Handler for purchase return approval side effects.
 *
 * Handlers are executed in priority order (lower numbers first).
 * Each handler is responsible for a single concern (SRP).
 */
interface PurchaseReturnApprovalHandlerInterface
{
    /**
     * Handle the approval side effect.
     *
     * @throws \Exception If handling fails (triggers transaction rollback)
     */
    public function handle(PurchaseReturn $purchaseReturn): void;

    /**
     * Get the priority of this handler.
     *
     * Lower numbers run first. Suggested ranges:
     * - 10-19: Inventory operations
     * - 20-29: Accounting/journal entries
     * - 30-39: Notifications
     */
    public function priority(): int;

    /**
     * Determine if this handler should process the given return.
     *
     * Allows conditional handling based on return state.
     */
    public function shouldHandle(PurchaseReturn $purchaseReturn): bool;
}
