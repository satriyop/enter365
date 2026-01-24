<?php

declare(strict_types=1);

namespace App\Services\Sales\Quotation;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Sales\QuotationNumberGeneratorInterface;
use App\Domain\Sales\Quotations\Enums\QuotationType;
use App\Domain\Sales\Quotations\QuotationDefaults;
use App\Domain\Sales\Quotations\QuotationDomainFactory;
use App\Domain\Sales\Quotations\QuotationItemCreator;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\Bom;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationVariantOption;
use App\Models\User;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithTransaction;
use Illuminate\Support\Collection;

/**
 * Handles CRUD operations for quotations.
 *
 * Extracted from QuotationService as part of the Coordinator Pattern refactoring.
 * This service focuses solely on create, update, and delete operations.
 *
 * @see \App\Services\Sales\QuotationService The coordinator service
 */
class QuotationCrudService
{
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    private const DEFAULT_MARGIN_PERCENT = 20;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private QuotationNumberGeneratorInterface $quotationNumberGenerator,
        private QuotationDefaults $defaults,
        private QuotationItemCreator $itemCreator,
        private QuotationDomainFactory $domainFactory,
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Create a new quotation with items.
     *
     * @param  array<string, mixed>  $data
     * @param  User|int|null  $user  The user creating the quotation
     */
    public function create(array $data, User|int|null $user = null): Quotation
    {
        $userId = $this->resolveUserId($user);

        return $this->executeInTransaction('create', function () use ($data, $userId) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            // Use domain-specific defaults
            $defaults = $this->defaults->getForCreate($data, $userId);
            $defaults['quotation_number'] = $this->quotationNumberGenerator->generateQuotationNumber();

            // Create via Eloquent
            $quotation = Quotation::create($defaults);

            // Create items using specialized creator
            $this->itemCreator->createItems($quotation, $items);
            $quotation->refresh();

            // Calculate totals using domain factory
            $this->domainFactory->applyTotals($quotation);
            $quotation->save();

            return $this->loadRelations($quotation);
        }, ['contact_id' => $data['contact_id'] ?? null]);
    }

    /**
     * Update a quotation.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Quotation $quotation, array $data): Quotation
    {
        if (! $this->domainFactory->stateMachine($quotation)->canEdit()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($quotation, 'Hanya penawaran draft yang dapat diubah.');
        }

        return $this->executeInTransaction('update', function () use ($quotation, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $quotation->update($data);

            if ($items !== null) {
                $quotation->items()->delete();
                $this->itemCreator->createItems($quotation, $items);
            }

            $quotation->refresh();
            $this->domainFactory->applyTotals($quotation);
            $quotation->save();

            return $this->loadRelations($quotation);
        }, ['quotation_id' => $quotation->id]);
    }

    /**
     * Delete a quotation.
     */
    public function delete(Quotation $quotation): bool
    {
        if (! $this->domainFactory->stateMachine($quotation)->canEdit()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotDelete($quotation, 'Hanya penawaran draft yang dapat dihapus.');
        }

        return $this->executeInTransaction('delete', function () use ($quotation) {
            $quotation->items()->delete();

            return $quotation->delete();
        }, ['quotation_id' => $quotation->id]);
    }

    /**
     * Create a quotation from a BOM.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromBom(array $data): Quotation
    {
        $bom = $this->validateBomForQuotation($data);

        $marginPercent = $data['margin_percent'] ?? self::DEFAULT_MARGIN_PERCENT;
        $expandItems = $data['expand_items'] ?? false;

        // Calculate selling price
        $bomCost = $bom->total_cost ?? 0;
        $sellingPrice = isset($data['selling_price'])
            ? (int) $data['selling_price']
            : (int) round($bomCost * (1 + ($marginPercent / 100)));

        return $this->executeInTransaction('create_from_bom', function () use ($data, $bom, $sellingPrice, $marginPercent, $expandItems) {
            $defaults = $this->defaults->forBom($data, $bom, $this->getUserId());
            $defaults['quotation_number'] = $this->quotationNumberGenerator->generateQuotationNumber();

            $quotation = Quotation::create($defaults);

            // Create items
            if ($expandItems) {
                $this->itemCreator->createFromBomExpanded($quotation, $bom, $marginPercent);
            } else {
                $this->itemCreator->createFromBomSingle($quotation, $bom, $sellingPrice);
            }

            $quotation->refresh();
            $this->domainFactory->applyTotals($quotation);
            $quotation->save();

            return $this->loadRelations($quotation);
        }, ['bom_id' => $bom->id, 'contact_id' => $data['contact_id'] ?? null]);
    }

    /**
     * Duplicate a quotation as a new draft.
     */
    public function duplicate(Quotation $quotation): Quotation
    {
        return $this->executeInTransaction('duplicate', function () use ($quotation) {
            $defaults = $this->defaults->forDuplication($quotation);
            $defaults['quotation_number'] = $this->quotationNumberGenerator->generateQuotationNumber();
            $defaults['created_by'] = $this->getUserId();

            return $this->createQuotationCopy($quotation, $defaults);
        }, ['quotation_id' => $quotation->id]);
    }

    /**
     * Create a revision of a quotation.
     */
    public function revise(Quotation $quotation): Quotation
    {
        if (! $this->domainFactory->stateMachine($quotation)->canRevise()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Quotation',
                'direvisi',
                $quotation->status->value,
                'disetujui, ditolak, atau kedaluwarsa'
            );
        }

        return $this->executeInTransaction('revise', function () use ($quotation) {
            $originalId = $quotation->original_quotation_id ?? $quotation->id;
            $nextRevision = $this->quotationNumberGenerator->getNextRevisionNumber($quotation);

            $defaults = $this->defaults->forRevision($quotation, $originalId, $nextRevision);
            $defaults['created_by'] = $this->getUserId();

            return $this->createQuotationCopy($quotation, $defaults);
        }, ['quotation_id' => $quotation->id]);
    }

    private function validateBomForQuotation(array $data): Bom
    {
        $bomId = $data['bom_id'] ?? null;
        if (! $bomId) {
            throw \App\Exceptions\Domain\BusinessRuleException::missingRequiredData('Quotation', 'BOM');
        }

        $bom = Bom::with(['items.product', 'product'])->find($bomId);
        if (! $bom) {
            throw new \App\Exceptions\Domain\EntityNotFoundException('Bom', $bomId);
        }

        if ($bom->status !== DocumentStatus::Active) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menggunakan BOM',
                'BOM harus memiliki status aktif'
            );
        }

        return $bom;
    }

    private function createQuotationCopy(Quotation $source, array $defaults): Quotation
    {
        $newQuotation = Quotation::create($defaults);
        $this->itemCreator->copyFromQuotation($source, $newQuotation);

        return $this->loadRelations($newQuotation);
    }

    private function resolveUserId(User|int|null $user): ?int
    {
        if ($user instanceof User) {
            return $user->id;
        }

        if (is_int($user)) {
            return $user;
        }

        return $this->getUserId();
    }

    private function loadRelations(Quotation $quotation): Quotation
    {
        return $quotation->load(['items', 'contact']);
    }

    // ─────────────────────────────────────────────────────────────
    // Variant Options (Multi-Option Quotations)
    // ─────────────────────────────────────────────────────────────

    /**
     * Sync variant options for a multi-option quotation.
     *
     * @param  array<int, array{bom_id: int, display_name: string, tagline?: string, is_recommended?: bool, selling_price: int, features?: array, specifications?: array, warranty_terms?: string}>  $options
     * @return Collection<int, QuotationVariantOption>
     */
    public function syncVariantOptions(Quotation $quotation, array $options): Collection
    {
        if (! $this->domainFactory->stateMachine($quotation)->canEdit()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($quotation, 'Penawaran ini tidak dapat diubah.');
        }

        return $this->executeInTransaction('sync_variant_options', function () use ($quotation, $options) {
            // Update quotation type to multi-option if not already
            if (! $quotation->isMultiOption()) {
                $quotation->update(['quotation_type' => QuotationType::MultiOption->value]);
            }

            // Delete existing and create new options
            $quotation->variantOptions()->delete();

            collect($options)->each(function ($option, $index) use ($quotation) {
                $quotation->variantOptions()->create([
                    'bom_id' => $option['bom_id'],
                    'display_name' => $option['display_name'],
                    'tagline' => $option['tagline'] ?? null,
                    'is_recommended' => $option['is_recommended'] ?? false,
                    'selling_price' => $option['selling_price'],
                    'features' => $option['features'] ?? null,
                    'specifications' => $option['specifications'] ?? null,
                    'warranty_terms' => $option['warranty_terms'] ?? null,
                    'sort_order' => $index,
                ]);
            });

            // Return with BOM relationship
            return $quotation->variantOptions()->with('bom')->orderBy('sort_order')->get();
        }, ['quotation_id' => $quotation->id, 'options_count' => count($options)]);
    }

    /**
     * Select a variant for a multi-option quotation.
     */
    public function selectVariant(Quotation $quotation, int $variantOptionId): Quotation
    {
        if (! $quotation->isMultiOption()) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'memilih varian',
                'Penawaran harus tipe multi-option'
            );
        }

        /** @var QuotationVariantOption|null $variantOption */
        $variantOption = QuotationVariantOption::find($variantOptionId);

        if (! $variantOption) {
            throw new \App\Exceptions\Domain\EntityNotFoundException('QuotationVariantOption', $variantOptionId);
        }

        if ($variantOption->quotation_id !== $quotation->id) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'memilih varian',
                'Pilihan varian tidak valid untuk penawaran ini'
            );
        }

        return $this->executeInTransaction('select_variant', function () use ($quotation, $variantOption) {
            $quotation->update([
                'selected_variant_id' => $variantOption->bom_id,
                'total_amount' => $variantOption->selling_price,
            ]);

            return $this->loadRelations($quotation);
        }, ['quotation_id' => $quotation->id, 'variant_id' => $variantOption->id]);
    }

    /**
     * Clear the selected variant for a multi-option quotation.
     */
    public function clearVariantSelection(Quotation $quotation): Quotation
    {
        if (! $quotation->isMultiOption()) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'membersihkan varian',
                'Penawaran harus tipe multi-option'
            );
        }

        return $this->executeInTransaction('clear_variant', function () use ($quotation) {
            $quotation->update([
                'selected_variant_id' => null,
            ]);

            return $this->loadRelations($quotation);
        }, ['quotation_id' => $quotation->id]);
    }
}
