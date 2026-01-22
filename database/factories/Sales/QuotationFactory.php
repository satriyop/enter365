<?php

namespace Database\Factories\Sales;

use App\Domain\Sales\Quotations\Enums\QuotationOutcome;
use App\Domain\Sales\Quotations\Enums\QuotationPriority;
use App\Domain\Sales\Quotations\Enums\QuotationType;
use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    private static int $sequenceNumber = 0;

    public function definition(): array
    {
        self::$sequenceNumber++;
        $prefix = 'QUO-'.now()->format('Ym').'-';
        $quotationNumber = $prefix.str_pad((string) self::$sequenceNumber, 4, '0', STR_PAD_LEFT);

        $quotationDate = $this->faker->dateTimeBetween('-1 month', 'now');
        $validityDays = config('accounting.quotation.default_validity_days', 30);
        $validUntil = (clone $quotationDate)->modify("+{$validityDays} days");

        $subtotal = $this->faker->randomElement([5000000, 10000000, 25000000, 50000000, 100000000]);
        $taxRate = 11.00;
        $taxAmount = (int) round($subtotal * ($taxRate / 100));
        $total = $subtotal + $taxAmount;

        return [
            'quotation_number' => $quotationNumber,
            'revision' => 0,
            'contact_id' => Contact::factory()->customer(),
            'quotation_date' => $quotationDate,
            'valid_until' => $validUntil,
            'reference' => $this->faker->optional()->bothify('REF-####'),
            'subject' => $this->faker->optional()->sentence(4),
            'status' => DocumentStatus::Draft,
            'currency' => 'IDR',
            'exchange_rate' => 1,
            'subtotal' => $subtotal,
            'discount_type' => null,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $total,
            'base_currency_total' => $total,
            'notes' => $this->faker->optional()->paragraph(),
            'terms_conditions' => Quotation::getDefaultTermsConditions(),
            'submitted_at' => null,
            'submitted_by' => null,
            'approved_at' => null,
            'approved_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
            'converted_to_invoice_id' => null,
            'converted_at' => null,
            'original_quotation_id' => null,
            'created_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Draft,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => User::factory(),
            'valid_until' => now()->addDays(30),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Approved,
            'submitted_at' => now()->subDay(),
            'submitted_by' => User::factory(),
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Rejected,
            'submitted_at' => now()->subDay(),
            'submitted_by' => User::factory(),
            'rejected_at' => now(),
            'rejected_by' => User::factory(),
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Expired,
            'quotation_date' => now()->subDays(60),
            'valid_until' => now()->subDays(30),
        ]);
    }

    public function converted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Converted,
            'submitted_at' => now()->subDays(3),
            'submitted_by' => User::factory(),
            'approved_at' => now()->subDays(2),
            'approved_by' => User::factory(),
            'converted_to_invoice_id' => Invoice::factory(),
            'converted_at' => now()->subDay(),
        ]);
    }

    public function forContact(Contact $contact): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => $contact->id,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }

    public function withPercentageDiscount(float $percent): static
    {
        return $this->state(function (array $attributes) use ($percent) {
            $discountAmount = (int) round($attributes['subtotal'] * ($percent / 100));
            $taxableAmount = $attributes['subtotal'] - $discountAmount;
            $taxAmount = (int) round($taxableAmount * ($attributes['tax_rate'] / 100));
            $total = $taxableAmount + $taxAmount;

            return [
                'discount_type' => 'percentage',
                'discount_value' => $percent,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
                'base_currency_total' => $total,
            ];
        });
    }

    public function withFixedDiscount(int $amount): static
    {
        return $this->state(function (array $attributes) use ($amount) {
            $taxableAmount = $attributes['subtotal'] - $amount;
            $taxAmount = (int) round($taxableAmount * ($attributes['tax_rate'] / 100));
            $total = $taxableAmount + $taxAmount;

            return [
                'discount_type' => 'fixed',
                'discount_value' => $amount,
                'discount_amount' => $amount,
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
                'base_currency_total' => $total,
            ];
        });
    }

    public function revision(Quotation $original): static
    {
        return $this->state(fn (array $attributes) => [
            'quotation_number' => $original->quotation_number,
            'revision' => $original->getNextRevisionNumber(),
            'original_quotation_id' => $original->original_quotation_id ?? $original->id,
            'contact_id' => $original->contact_id,
            'status' => DocumentStatus::Draft,
        ]);
    }

    public function validFor(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'quotation_date' => now(),
            'valid_until' => now()->addDays($days),
        ]);
    }

    public function withoutTax(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'tax_rate' => 0,
                'tax_amount' => 0,
                'total_amount' => $attributes['subtotal'] - $attributes['discount_amount'],
                'base_currency_total' => $attributes['subtotal'] - $attributes['discount_amount'],
            ];
        });
    }

    public function withFollowUp(int $daysFromNow = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'next_follow_up_at' => now()->addDays($daysFromNow),
        ]);
    }

    public function overdueFollowUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_follow_up_at' => now()->subDays(2),
        ]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => $user->id,
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => QuotationPriority::High->value,
        ]);
    }

    public function urgentPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => QuotationPriority::Urgent->value,
        ]);
    }

    public function won(string $reason = 'harga_kompetitif'): static
    {
        return $this->approved()->state(fn (array $attributes) => [
            'outcome' => QuotationOutcome::Won->value,
            'won_reason' => $reason,
            'outcome_at' => now(),
        ]);
    }

    public function lost(string $reason = 'harga_tinggi', ?string $competitor = null): static
    {
        return $this->approved()->state(fn (array $attributes) => [
            'outcome' => QuotationOutcome::Lost->value,
            'lost_reason' => $reason,
            'lost_to_competitor' => $competitor,
            'outcome_at' => now(),
        ]);
    }

    public function multiOption(): static
    {
        return $this->state(fn (array $attributes) => [
            'quotation_type' => QuotationType::MultiOption->value,
        ]);
    }

    public function singleOption(): static
    {
        return $this->state(fn (array $attributes) => [
            'quotation_type' => QuotationType::Single->value,
        ]);
    }
}
