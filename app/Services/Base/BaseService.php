<?php

declare(strict_types=1);

namespace App\Services\Base;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithRequestCache;
use App\Services\Base\Traits\WithTransaction;

/**
 * Base class for all application services.
 *
 * Provides common functionality through composable traits:
 * - Transaction management (WithTransaction)
 * - Event dispatching (WithEventDispatching)
 * - Operation context (WithOperationContext)
 *
 * Services can extend BaseService and add traits as needed.
 *
 * @example
 * class BudgetService extends BaseService
 * {
 *     public function create(array $data): Budget
 *     {
 *         return $this->executeInTransaction('create', function () use ($data) {
 *             return Budget::create($data);
 *         });
 *     }
 * }
 * @example
 * class InvoiceService extends BaseService
 * {
 *     use WithDocuments;
 *
 *     protected function getDocumentNumberField(): string { return 'invoice_number'; }
 *     protected function getDocumentNumberPrefix(): string { return 'INV-'.now()->format('Ym').'-'; }
 *     protected function getDocumentNumberConfig(): array { return ['table' => 'invoices', 'column' => 'invoice_number']; }
 *     protected function getItemRelation(): string { return 'items'; }
 * }
 */
abstract class BaseService
{
    use WithEventDispatching;
    use WithOperationContext;
    use WithRequestCache;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }
}
