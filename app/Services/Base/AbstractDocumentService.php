<?php

declare(strict_types=1);

namespace App\Services\Base;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Repositories\RepositoryInterface;
use App\Contracts\Shared\DocumentNumberGeneratorInterface;
use App\Services\Base\Traits\WithDocuments;

/**
 * Abstract base class for document services.
 *
 * Documents: Invoices, Bills, Quotations, PurchaseOrders, WorkOrders, etc.
 *
 * @deprecated Services should use BaseService + WithDocuments trait directly.
 * This class maintained for backward compatibility.
 *
 * Provides common CRUD operations with:
 * - Repository-based data access
 * - Result objects for consistent returns
 * - Transaction management
 * - Logging and event dispatching
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @template TRepository of RepositoryInterface<TModel>
 */
abstract class AbstractDocumentService extends BaseService
{
    use WithDocuments;

    /**
     * Constructor with strict dependency injection.
     *
     * EventDispatcher and Logger are REQUIRED (inherited from BaseService).
     * Repository and NumberGenerator are optional (some services use model directly).
     *
     * @param  EventDispatcherInterface  $eventDispatcher  Required - for domain events
     * @param  ContextualLoggerInterface  $logger  Required - for operation logging
     * @param  RepositoryInterface|null  $repository  Optional - for data access
     * @param  DocumentNumberGeneratorInterface|null  $numberGenerator  Optional - for document numbering
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        ?RepositoryInterface $repository = null,
        ?DocumentNumberGeneratorInterface $numberGenerator = null
    ) {
        parent::__construct($eventDispatcher, $logger);

        $this->repository = $repository;
        $this->numberGenerator = $numberGenerator;
    }
}
