<?php

declare(strict_types=1);

namespace App\Contracts\Handlers;

use App\Models\Sales\SalesReturn;

/**
 * Contract for handlers that process sales return approval side effects.
 *
 * Each handler is responsible for a single concern (SRP):
 * - Inventory processing
 * - Journal entry creation
 * - Notification sending
 * etc.
 */
interface SalesReturnApprovalHandlerInterface
{
    /**
     * Handle the sales return approval side effect.
     *
     * @param  SalesReturn  $salesReturn  The approved sales return
     *
     * @throws \Exception If handling fails (triggers transaction rollback)
     */
    public function handle(SalesReturn $salesReturn): void;

    /**
     * Get the priority for this handler.
     * Lower numbers run first (e.g., 10 before 20).
     */
    public function priority(): int;

    /**
     * Check if this handler should process the given sales return.
     */
    public function shouldHandle(SalesReturn $salesReturn): bool;
}
