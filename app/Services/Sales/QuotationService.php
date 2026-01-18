<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Sales\QuotationCalculatorInterface;
use App\Contracts\Sales\QuotationNumberGeneratorInterface;
use App\Contracts\Sales\QuotationServiceInterface;
use App\Domain\Sales\Quotations\QuotationDefaults;
use App\Domain\Sales\Quotations\QuotationItemCreator;
use App\Domain\Sales\Quotations\QuotationStatistics;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\Bom;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationService implements QuotationServiceInterface
{
    private const EXPIRABLE_STATUSES = [
        DocumentStatus::Draft,
        DocumentStatus::Submitted,
    ];

    private const DEFAULT_MARGIN_PERCENT = 20;

    private const DEFAULT_VALIDITY_DAYS = 30;

    public function __construct(
        private QuotationConversionService $conversionService,
        private QuotationNumberGeneratorInterface $numberGenerator,
        private QuotationDefaults $defaults,
        private QuotationItemCreator $itemCreator,
        private QuotationStatistics $statistics,
        private QuotationCalculatorInterface $calculator
    ) {}

    /**
     * Create a new quotation with items.
     *
     * @param  array<string, mixed>  $data
     * @param  User|int|null  $user  The user creating the quotation (optional, defaults to auth)
     */
    public function create(array $data, User|int|null $user = null): Quotation
    {
        $userId = $this->resolveUserId($user);

        return DB::transaction(function () use ($data, $userId) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $defaults = $this->defaults->getForCreate($data, $userId);
            $defaults['quotation_number'] = $this->numberGenerator->generateQuotationNumber();

            $quotation = Quotation::create($defaults);

            $this->itemCreator->createItems($quotation, $items);
            $quotation->refresh();
            $quotation->calculateTotals();

            return $quotation->load('items', 'contact');
        });
    }

    /**
     * Resolve user ID from various input types.
     */
    private function resolveUserId(User|int|null $user): int
    {
        if ($user instanceof User) {
            return $user->id;
        }

        if (is_int($user)) {
            return $user;
        }

        return (int) auth()->id();
    }

    /**
     * Create a quotation from a BOM.
     *
     * This allows salespeople to pick a specific BOM (e.g., from a variant group)
     * and auto-generate a quotation with proper pricing.
     *
     * @param  array<string, mixed>  $data  {
     *                                      bom_id: int,           Required - BOM to create quotation from
     *                                      contact_id: int,       Required - Customer contact
     *                                      margin_percent?: float, Margin percentage to add on top of BOM cost (default: 20)
     *                                      selling_price?: int,   Override: direct selling price (ignores margin)
     *                                      expand_items?: bool,   Expand BOM items as quotation lines (default: false)
     *                                      quotation_date?: date,
     *                                      valid_until?: date,
     *                                      subject?: string,
     *                                      reference?: string,
     *                                      notes?: string,
     *                                      terms_conditions?: string,
     *                                      tax_rate?: float,
     *                                      currency?: string,
     *                                      exchange_rate?: float,
     *                                      }
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

        return DB::transaction(function () use ($data, $bom, $sellingPrice, $marginPercent, $expandItems) {
            $defaults = $this->defaults->forBom($data, $bom, (int) auth()->id());
            $defaults['quotation_number'] = $this->numberGenerator->generateQuotationNumber();

            $quotation = Quotation::create($defaults);

            // Create items
            if ($expandItems) {
                $this->itemCreator->createFromBomExpanded($quotation, $bom, $marginPercent);
            } else {
                $this->itemCreator->createFromBomSingle($quotation, $bom, $sellingPrice);
            }

            // Calculate totals
            $quotation->refresh();
            $quotation->calculateTotals();

            return $quotation->load('items', 'contact');
        });
    }

    /**
     * Validate BOM data and return BOM model.
     */
    private function validateBomForQuotation(array $data): Bom
    {
        $bomId = $data['bom_id'] ?? null;
        if (! $bomId) {
            throw new InvalidArgumentException('BOM harus dipilih.');
        }

        $bom = Bom::with(['items.product', 'product'])->find($bomId);
        if (! $bom) {
            throw new InvalidArgumentException('BOM tidak ditemukan.');
        }

        if ($bom->status !== DocumentStatus::Active) {
            throw new InvalidArgumentException('Hanya BOM dengan status aktif yang dapat digunakan.');
        }

        return $bom;
    }

    /**
     * Update a quotation.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Quotation $quotation, array $data): Quotation
    {
        if (! $quotation->isEditable()) {
            throw new InvalidArgumentException('Hanya penawaran draft yang dapat diubah.');
        }

        return DB::transaction(function () use ($quotation, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $quotation->update($data);

            if ($items !== null) {
                $quotation->items()->delete();
                $this->itemCreator->createItems($quotation, $items);
            }

            $quotation->refresh();
            $quotation->calculateTotals();

            return $quotation->load('items', 'contact');
        });
    }

    /**
     * Submit quotation for approval.
     */
    public function submit(Quotation $quotation, ?int $userId = null): Quotation
    {
        if (! $quotation->stateMachine()->canSubmit()) {
            throw new InvalidArgumentException(
                'Penawaran tidak dapat diajukan. Pastikan status draft dan memiliki item.'
            );
        }

        $quotation->transitionTo(DocumentStatus::Submitted, $userId);

        return $quotation->fresh(['items', 'contact']);
    }

    /**
     * Approve a quotation.
     */
    public function approve(Quotation $quotation, ?int $userId = null): Quotation
    {
        if (! $quotation->stateMachine()->canApprove()) {
            throw new InvalidArgumentException(
                'Penawaran tidak dapat disetujui. Pastikan sudah diajukan dan belum kedaluwarsa.'
            );
        }

        $quotation->transitionTo(DocumentStatus::Approved, $userId);

        return $quotation->fresh(['items', 'contact']);
    }

    /**
     * Reject a quotation.
     */
    public function reject(Quotation $quotation, string $reason, ?int $userId = null): Quotation
    {
        if (! $quotation->stateMachine()->canReject()) {
            throw new InvalidArgumentException(
                'Penawaran tidak dapat ditolak. Pastikan sudah diajukan.'
            );
        }

        if (empty($reason)) {
            throw new InvalidArgumentException('Alasan penolakan harus diisi.');
        }

        $quotation->transitionTo(DocumentStatus::Rejected, $userId, ['rejection_reason' => $reason]);

        return $quotation->fresh(['items', 'contact']);
    }

    /**
     * Create a revision of a quotation.
     */
    public function revise(Quotation $quotation): Quotation
    {
        if (! $quotation->canRevise()) {
            throw new InvalidArgumentException('Penawaran tidak dapat direvisi. Pastikan sudah disetujui, ditolak, atau kedaluwarsa.');
        }

        return DB::transaction(function () use ($quotation) {
            $originalId = $quotation->original_quotation_id ?? $quotation->id;
            $nextRevision = $this->numberGenerator->getNextRevisionNumber($quotation);

            $defaults = $this->defaults->forRevision($quotation, $originalId, $nextRevision);
            $defaults['created_by'] = auth()->id();

            return $this->createQuotationCopy($quotation, $defaults);
        });
    }

    /**
     * Convert an approved quotation to an invoice (delegates to conversion service).
     */
    public function convertToInvoice(Quotation $quotation): Invoice
    {
        return $this->conversionService->convertToInvoice($quotation);
    }

    /**
     * Duplicate a quotation as a new draft.
     */
    public function duplicate(Quotation $quotation): Quotation
    {
        return DB::transaction(function () use ($quotation) {
            $defaults = $this->defaults->forDuplication($quotation);
            $defaults['quotation_number'] = $this->numberGenerator->generateQuotationNumber();
            $defaults['created_by'] = auth()->id();

            return $this->createQuotationCopy($quotation, $defaults);
        });
    }

    /**
     * Create a quotation copy with items copied from source.
     */
    private function createQuotationCopy(Quotation $source, array $defaults): Quotation
    {
        $newQuotation = Quotation::create($defaults);
        $this->itemCreator->copyFromQuotation($source, $newQuotation);

        return $newQuotation->load('items', 'contact');
    }

    /**
     * Mark expired quotations.
     *
     * @return int Number of quotations marked as expired
     */
    public function markExpired(): int
    {
        return Quotation::query()
            ->whereIn('status', self::EXPIRABLE_STATUSES)
            ->where('valid_until', '<', now()->startOfDay())
            ->update(['status' => DocumentStatus::Expired]);
    }

    /**
     * Get quotation statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->statistics->get($startDate, $endDate);
    }
}
