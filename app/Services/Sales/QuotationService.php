<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Services\Domains\QuotationNumberGeneratorInterface;
use App\Contracts\Services\Domains\QuotationServiceInterface;
use App\Domain\Sales\Quotations\DiscountCalculator;
use App\Domain\Sales\Quotations\Enums\QuotationType;
use App\Domain\Sales\Quotations\QuotationDefaults;
use App\Domain\Sales\Quotations\QuotationItemCreator;
use App\Domain\Sales\Quotations\QuotationStatistics;
use App\Domain\Sales\Quotations\TaxCalculator;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\Bom;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationService implements QuotationServiceInterface
{
    public function __construct(
        private QuotationConversionService $conversionService,
        private QuotationNumberGeneratorInterface $numberGenerator,
        private QuotationDefaults $defaults,
        private QuotationItemCreator $itemCreator,
        private QuotationStatistics $statistics
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
            $taxRate = $defaults['tax_rate'];

            $quotation = Quotation::create($defaults);

            $this->itemCreator->createItems($quotation, $items);
            $this->calculateTotals($quotation, $taxRate);

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
     * Calculate and update quotation totals using domain calculators.
     */
    private function calculateTotals(Quotation $quotation, float $taxRate): void
    {
        $subtotal = $quotation->items->sum('line_total');

        $discountCalculator = new DiscountCalculator(
            $quotation->discount_type,
            (float) ($quotation->discount_value ?? 0)
        );
        $discountAmount = $discountCalculator->calculate($subtotal);

        // Calculate tax on (subtotal - discount)
        $taxableAmount = $subtotal - $discountAmount;
        $taxCalculator = new TaxCalculator((int) round($taxRate));
        $taxAmount = $taxCalculator->calculateFromSubtotal($taxableAmount);

        $total = $taxableAmount + $taxAmount;

        $baseCurrencyTotal = $quotation->currency !== 'IDR' && $quotation->exchange_rate > 0
            ? (int) round($total * $quotation->exchange_rate)
            : $total;

        $quotation->subtotal = $subtotal;
        $quotation->discount_amount = $discountAmount;
        $quotation->tax_amount = $taxAmount;
        $quotation->total = $total;
        $quotation->base_currency_total = $baseCurrencyTotal;
        $quotation->save();
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

        $marginPercent = $data['margin_percent'] ?? 20;
        $expandItems = $data['expand_items'] ?? false;

        // Calculate selling price
        $bomCost = $bom->total_cost ?? 0;
        if (isset($data['selling_price'])) {
            $sellingPrice = (int) $data['selling_price'];
        } else {
            $sellingPrice = (int) round($bomCost * (1 + ($marginPercent / 100)));
        }

        return DB::transaction(function () use ($data, $bom, $sellingPrice, $marginPercent, $expandItems) {
            $taxRate = $data['tax_rate'] ?? config('accounting.tax.default_rate', 11.00);
            $quotationDate = $data['quotation_date'] ?? now();
            $validityDays = config('accounting.quotation.default_validity_days', 30);

            // Build subject from BOM if not provided
            $subject = $data['subject'] ?? $bom->name;
            if ($bom->variant_name) {
                $subject .= ' - '.$bom->variant_name;
            }

            // Create quotation
            $quotation = Quotation::create([
                'quotation_number' => $this->numberGenerator->generateQuotationNumber(),
                'revision' => 0,
                'contact_id' => $data['contact_id'],
                'quotation_date' => $quotationDate,
                'valid_until' => $data['valid_until'] ?? now()->parse($quotationDate)->addDays($validityDays),
                'reference' => $data['reference'] ?? $bom->bom_number,
                'subject' => $subject,
                'quotation_type' => QuotationType::Single->value,
                'source_bom_id' => $bom->id,
                'status' => DocumentStatus::Draft,
                'currency' => $data['currency'] ?? 'IDR',
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'tax_rate' => $taxRate,
                'subtotal' => 0,
                'discount_type' => null,
                'discount_value' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total' => 0,
                'base_currency_total' => 0,
                'notes' => $data['notes'] ?? "Dibuat dari BOM: {$bom->bom_number}\nMargin: {$marginPercent}%\nBiaya BOM: ".number_format($bom->total_cost, 0, ',', '.'),
                'terms_conditions' => $data['terms_conditions'] ?? Quotation::getDefaultTermsConditions(),
                'created_by' => auth()->id(),
            ]);

            // Create items
            if ($expandItems) {
                $this->itemCreator->createFromBomExpanded($quotation, $bom, $marginPercent);
            } else {
                $this->itemCreator->createFromBomSingle($quotation, $bom, $sellingPrice);
            }

            // Calculate totals using domain calculators
            $quotation->refresh();
            $this->calculateTotals($quotation, (float) $quotation->tax_rate);

            return $quotation->load('items', 'contact');
        });
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
            $this->calculateTotals($quotation, (float) $quotation->tax_rate);

            return $quotation->load('items', 'contact');
        });
    }

    /**
     * Submit quotation for approval.
     */
    public function submit(Quotation $quotation, ?int $userId = null): Quotation
    {
        if (! $quotation->canSubmit()) {
            throw new InvalidArgumentException('Penawaran tidak dapat diajukan. Pastikan status draft dan memiliki item.');
        }

        $quotation->update([
            'status' => DocumentStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => $userId ?? auth()->id(),
        ]);

        return $quotation->fresh(['items', 'contact']);
    }

    /**
     * Approve a quotation.
     */
    public function approve(Quotation $quotation, ?int $userId = null): Quotation
    {
        if (! $quotation->canApprove()) {
            throw new InvalidArgumentException('Penawaran tidak dapat disetujui. Pastikan sudah diajukan dan belum kedaluwarsa.');
        }

        $quotation->update([
            'status' => DocumentStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $userId ?? auth()->id(),
        ]);

        return $quotation->fresh(['items', 'contact']);
    }

    /**
     * Reject a quotation.
     */
    public function reject(Quotation $quotation, string $reason, ?int $userId = null): Quotation
    {
        if (! $quotation->canReject()) {
            throw new InvalidArgumentException('Penawaran tidak dapat ditolak. Pastikan sudah diajukan.');
        }

        if (empty($reason)) {
            throw new InvalidArgumentException('Alasan penolakan harus diisi.');
        }

        $quotation->update([
            'status' => DocumentStatus::Rejected,
            'rejected_at' => now(),
            'rejected_by' => $userId ?? auth()->id(),
            'rejection_reason' => $reason,
        ]);

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

            $newQuotation = Quotation::create($defaults);

            $this->itemCreator->copyFromQuotation($quotation, $newQuotation);

            return $newQuotation->load('items', 'contact');
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

            $newQuotation = Quotation::create($defaults);

            $this->itemCreator->copyFromQuotation($quotation, $newQuotation);

            return $newQuotation->load('items', 'contact');
        });
    }

    /**
     * Mark expired quotations.
     *
     * @return int Number of quotations marked as expired
     */
    public function markExpired(): int
    {
        return Quotation::query()
            ->whereIn('status', [DocumentStatus::Draft, DocumentStatus::Submitted])
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
