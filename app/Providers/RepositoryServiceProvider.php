<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\Inventory\ProductStockRepositoryInterface;
use App\Contracts\Repositories\Manufacturing\WorkOrderRepositoryInterface;
use App\Contracts\Repositories\Sales\InvoiceRepositoryInterface;
use App\Contracts\Repositories\Sales\QuotationRepositoryInterface;
use App\Infrastructure\Repositories\Inventory\EloquentProductStockRepository;
use App\Infrastructure\Repositories\Manufacturing\EloquentWorkOrderRepository;
use App\Infrastructure\Repositories\Sales\EloquentInvoiceRepository;
use App\Infrastructure\Repositories\Sales\EloquentQuotationRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for repository bindings.
 *
 * Registers interface-to-implementation bindings for all repositories.
 * This enables dependency injection and easy swapping of implementations
 * (e.g., using in-memory repositories for testing).
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All repository bindings.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        InvoiceRepositoryInterface::class => EloquentInvoiceRepository::class,
        QuotationRepositoryInterface::class => EloquentQuotationRepository::class,
        WorkOrderRepositoryInterface::class => EloquentWorkOrderRepository::class,
        ProductStockRepositoryInterface::class => EloquentProductStockRepository::class,
    ];

    /**
     * Register repository bindings.
     */
    public function register(): void
    {
        foreach ($this->bindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }
}
