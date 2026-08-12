<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Purchasing\PurchaseOrders\PurchaseOrderDomainFactory;
use App\Enums\DocumentStatus;
use App\Enums\MrpSuggestionStatus;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\MrpRun;
use App\Models\Manufacturing\MrpSuggestion;
use App\Models\Manufacturing\SubcontractorWorkOrder;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Services\Base\BaseService;

/**
 * Service for MRP suggestion management and conversion.
 *
 * Handles generating suggestions from shortages, accepting/rejecting suggestions,
 * and converting suggestions to Purchase Orders, Work Orders, or Subcontractor Work Orders.
 */
class MrpSuggestionService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private PurchaseOrderDomainFactory $purchaseOrderDomainFactory,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Generate suggestions for shortages.
     */
    public function generateSuggestions(MrpRun $run): void
    {
        // Clear existing suggestions
        $run->suggestions()->delete();

        $shortages = $run->demands()
            ->where('quantity_short', '>', 0)
            ->with(['product'])
            ->get();

        // Group by product to consolidate suggestions
        $groupedShortages = $shortages->groupBy('product_id');

        foreach ($groupedShortages as $productId => $productShortages) {
            $product = $productShortages->first()->product;
            if (! $product) {
                continue;
            }

            // Sum up all shortages for this product
            $totalShort = $productShortages->sum('quantity_short');
            $earliestDue = $productShortages->min('required_date');

            // Apply MOQ and order multiple
            $suggestedQty = $this->applyOrderConstraints($product, $totalShort);

            // Determine suggestion type based on procurement type
            $suggestionType = $this->getSuggestionType($product);

            // Calculate order date based on lead time
            $leadTimeDays = $product->lead_time_days ?? 0;
            $orderDate = $earliestDue->copy()->subDays($leadTimeDays);

            // Determine priority
            $priority = $this->calculatePriority($orderDate);

            MrpSuggestion::create([
                'mrp_run_id' => $run->id,
                'product_id' => $productId,
                'suggestion_type' => $suggestionType,
                'action' => MrpSuggestion::ACTION_CREATE,
                'suggested_order_date' => $orderDate,
                'suggested_due_date' => $earliestDue,
                'quantity_required' => $totalShort,
                'suggested_quantity' => $suggestedQty,
                'suggested_supplier_id' => $product->default_supplier_id,
                'suggested_warehouse_id' => $run->warehouse_id,
                'estimated_unit_cost' => $product->purchase_price ?? 0,
                'estimated_total_cost' => (int) round($suggestedQty * ($product->purchase_price ?? 0)),
                'priority' => $priority,
                'status' => MrpSuggestionStatus::Pending,
                'reason' => $this->generateReason($product, $totalShort, $productShortages->count()),
            ]);
        }
    }

    /**
     * Accept a suggestion.
     */
    public function acceptSuggestion(MrpSuggestion $suggestion): MrpSuggestion
    {
        $suggestion->accept();

        return $suggestion->fresh();
    }

    /**
     * Reject a suggestion.
     */
    public function rejectSuggestion(MrpSuggestion $suggestion, ?string $reason = null): MrpSuggestion
    {
        $suggestion->reject($reason);

        return $suggestion->fresh();
    }

    /**
     * Update suggestion quantity.
     */
    public function updateSuggestionQuantity(MrpSuggestion $suggestion, float $quantity): MrpSuggestion
    {
        if (! $suggestion->isPending() && ! $suggestion->isAccepted()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($suggestion, 'Hanya saran pending atau diterima yang dapat diubah kuantitasnya.');
        }

        $suggestion->adjusted_quantity = $quantity;
        $suggestion->calculateEstimatedCosts();
        $suggestion->save();

        return $suggestion->fresh();
    }

    /**
     * Convert suggestion to Purchase Order.
     */
    public function convertToPurchaseOrder(MrpSuggestion $suggestion, ?int $userId = null): PurchaseOrder
    {
        if (! $suggestion->canBeConverted()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'MRP Suggestion',
                'dikonversi',
                $suggestion->status->value,
                'accepted'
            );
        }

        if ($suggestion->suggestion_type !== MrpSuggestion::TYPE_PURCHASE) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'konversi suggestion ke PO',
                'Hanya suggestion pembelian yang dapat dikonversi ke PO'
            );
        }

        return $this->executeInTransaction('convert_to_purchase_order', function () use ($suggestion, $userId) {
            $product = $suggestion->product;
            $quantity = $suggestion->getEffectiveQuantity();

            // Create PO
            $po = PurchaseOrder::create([
                'contact_id' => $suggestion->suggested_supplier_id,
                'po_date' => now(),
                'expected_date' => $suggestion->suggested_due_date,
                'reference' => 'MRP: '.$suggestion->mrpRun->run_number,
                'subject' => 'PO dari MRP untuk '.$product->name,
                'status' => DocumentStatus::Draft,
                'currency' => 'IDR',
                'exchange_rate' => 1,
                'tax_rate' => config('accounting.tax.default_rate', 11.00),
                'subtotal' => $suggestion->estimated_total_cost,
                'tax_amount' => 0,
                'total_amount' => $suggestion->estimated_total_cost,
                'base_currency_total' => $suggestion->estimated_total_cost,
                'created_by' => $userId ?? $this->getUserId(),
            ]);

            // Create PO item
            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => $quantity,
                'unit' => $product->unit ?? 'unit',
                'unit_price' => $suggestion->estimated_unit_cost ?? 0,
                'line_total' => $suggestion->estimated_total_cost ?? 0,
            ]);

            // Recalculate PO totals via domain factory (no app() in models)
            $po->load('items');
            $this->purchaseOrderDomainFactory->applyTotals($po);
            $po->save();

            // Mark suggestion as converted
            $suggestion->markAsConverted(PurchaseOrder::class, $po->id, $userId);

            return $po->fresh(['items', 'contact']);
        }, ['suggestion_id' => $suggestion->id, 'product_id' => $suggestion->product_id]);
    }

    /**
     * Convert suggestion to Work Order.
     */
    public function convertToWorkOrder(MrpSuggestion $suggestion, ?int $userId = null): WorkOrder
    {
        if (! $suggestion->canBeConverted()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'MRP Suggestion',
                'dikonversi',
                $suggestion->status->value,
                'accepted'
            );
        }

        if ($suggestion->suggestion_type !== MrpSuggestion::TYPE_WORK_ORDER) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'konversi suggestion ke WO',
                'Hanya suggestion work order yang dapat dikonversi ke WO'
            );
        }

        return $this->executeInTransaction('convert_to_work_order', function () use ($suggestion, $userId) {
            $product = $suggestion->product;
            $quantity = $suggestion->getEffectiveQuantity();

            // Find BOM for product
            $bom = Bom::where('product_id', $product->id)
                ->where('status', DocumentStatus::Active)
                ->first();

            $woService = app(WorkOrderService::class);

            if ($bom) {
                $wo = $woService->createFromBom($bom, [
                    'quantity' => $quantity,
                    'warehouse_id' => $suggestion->suggested_warehouse_id,
                    'planned_start_date' => $suggestion->suggested_order_date,
                    'planned_end_date' => $suggestion->suggested_due_date,
                    'notes' => 'Dibuat dari MRP: '.$suggestion->mrpRun->run_number,
                    'created_by' => $userId ?? $this->getUserId(),
                ]);
            } else {
                $wo = $woService->create([
                    'product_id' => $product->id,
                    'type' => WorkOrder::TYPE_PRODUCTION,
                    'name' => 'Produksi '.$product->name,
                    'quantity_ordered' => $quantity,
                    'warehouse_id' => $suggestion->suggested_warehouse_id,
                    'planned_start_date' => $suggestion->suggested_order_date,
                    'planned_end_date' => $suggestion->suggested_due_date,
                    'notes' => 'Dibuat dari MRP: '.$suggestion->mrpRun->run_number,
                    'created_by' => $userId ?? $this->getUserId(),
                ]);
            }

            // Mark suggestion as converted
            $suggestion->markAsConverted(WorkOrder::class, $wo->id, $userId);

            return $wo;
        }, ['suggestion_id' => $suggestion->id, 'product_id' => $suggestion->product_id]);
    }

    /**
     * Convert suggestion to Subcontractor Work Order.
     */
    public function convertToSubcontractorWorkOrder(
        MrpSuggestion $suggestion,
        int $subcontractorId,
        ?int $userId = null
    ): SubcontractorWorkOrder {
        if (! $suggestion->canBeConverted()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'MRP Suggestion',
                'dikonversi',
                $suggestion->status->value,
                'accepted'
            );
        }

        if ($suggestion->suggestion_type !== MrpSuggestion::TYPE_SUBCONTRACT) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'konversi suggestion ke SC WO',
                'Hanya suggestion subkontrak yang dapat dikonversi ke SC WO'
            );
        }

        return $this->executeInTransaction('convert_to_subcontractor_wo', function () use ($suggestion, $subcontractorId, $userId) {
            $product = $suggestion->product;
            $quantity = $suggestion->getEffectiveQuantity();

            $scWo = SubcontractorWorkOrder::create([
                'subcontractor_id' => $subcontractorId,
                'name' => 'Subkontrak '.$product->name,
                'description' => "Produksi {$quantity} {$product->unit} {$product->name}",
                'scope_of_work' => 'Dibuat dari MRP: '.$suggestion->mrpRun->run_number,
                'status' => DocumentStatus::Draft,
                'agreed_amount' => $suggestion->estimated_total_cost ?? 0,
                'scheduled_start_date' => $suggestion->suggested_order_date,
                'scheduled_end_date' => $suggestion->suggested_due_date,
                'created_by' => $userId ?? $this->getUserId(),
            ]);

            // Mark suggestion as converted
            $suggestion->markAsConverted(SubcontractorWorkOrder::class, $scWo->id, $userId);

            return $scWo->fresh(['subcontractor']);
        }, ['suggestion_id' => $suggestion->id, 'subcontractor_id' => $subcontractorId]);
    }

    /**
     * Bulk accept suggestions.
     *
     * @param  array<int>  $suggestionIds
     */
    public function bulkAccept(array $suggestionIds): int
    {
        $count = 0;

        foreach ($suggestionIds as $id) {
            $suggestion = MrpSuggestion::find($id);
            if ($suggestion && $suggestion->canBeAccepted()) {
                $suggestion->accept();
                $count++;
            }
        }

        return $count;
    }

    /**
     * Bulk reject suggestions.
     *
     * @param  array<int>  $suggestionIds
     */
    public function bulkReject(array $suggestionIds, ?string $reason = null): int
    {
        $count = 0;

        foreach ($suggestionIds as $id) {
            $suggestion = MrpSuggestion::find($id);
            if ($suggestion && $suggestion->canBeRejected()) {
                $suggestion->reject($reason);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Apply MOQ and order multiple constraints.
     */
    private function applyOrderConstraints(Product $product, float $requiredQty): float
    {
        $moq = (float) ($product->min_order_qty ?? 1);
        $multiple = (float) ($product->order_multiple ?? 1);

        // Apply MOQ
        $qty = max($requiredQty, $moq);

        // Apply order multiple (round up)
        if ($multiple > 1) {
            $qty = ceil($qty / $multiple) * $multiple;
        }

        return $qty;
    }

    /**
     * Get suggestion type based on product procurement type.
     */
    private function getSuggestionType(Product $product): string
    {
        return match ($product->procurement_type) {
            'buy' => MrpSuggestion::TYPE_PURCHASE,
            'make' => MrpSuggestion::TYPE_WORK_ORDER,
            'subcontract' => MrpSuggestion::TYPE_SUBCONTRACT,
            default => MrpSuggestion::TYPE_PURCHASE,
        };
    }

    /**
     * Calculate priority based on urgency.
     */
    private function calculatePriority(\DateTimeInterface $orderDate): string
    {
        $daysUntilOrder = now()->diffInDays($orderDate, false);

        if ($daysUntilOrder < 0) {
            return MrpSuggestion::PRIORITY_URGENT;
        }
        if ($daysUntilOrder <= 3) {
            return MrpSuggestion::PRIORITY_HIGH;
        }
        if ($daysUntilOrder <= 7) {
            return MrpSuggestion::PRIORITY_NORMAL;
        }

        return MrpSuggestion::PRIORITY_LOW;
    }

    /**
     * Generate reason text for suggestion.
     */
    private function generateReason(Product $product, float $shortage, int $demandCount): string
    {
        $type = match ($product->procurement_type) {
            'buy' => 'dibeli',
            'make' => 'diproduksi',
            'subcontract' => 'disubkontrakkan',
            default => 'diadakan',
        };

        return "Kekurangan {$shortage} {$product->unit} untuk {$demandCount} permintaan. Produk perlu {$type}.";
    }
}
