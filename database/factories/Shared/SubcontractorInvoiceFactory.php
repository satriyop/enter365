<?php

namespace Database\Factories\Shared;

use App\Models\Contacts\Contact;
use App\Models\Manufacturing\SubcontractorWorkOrder;
use App\Models\Purchasing\Bill;
use App\Models\Shared\SubcontractorInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubcontractorInvoice>
 */
class SubcontractorInvoiceFactory extends Factory
{
    protected $model = SubcontractorInvoice::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $grossAmount = $this->faker->numberBetween(1000000, 50000000);
        $retentionHeld = (int) round($grossAmount * 0.05);
        $otherDeductions = 0;
        $netAmount = $grossAmount - $retentionHeld - $otherDeductions;

        $invoiceDate = $this->faker->dateTimeBetween('-1 week', 'now');
        $dueDate = $this->faker->dateTimeBetween($invoiceDate, '+30 days');

        return [
            'invoice_number' => 'SCI-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'subcontractor_work_order_id' => SubcontractorWorkOrder::factory(),
            'subcontractor_id' => Contact::factory()->subcontractor(),
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'gross_amount' => $grossAmount,
            'retention_held' => $retentionHeld,
            'other_deductions' => $otherDeductions,
            'net_amount' => $netAmount,
            'description' => $this->faker->optional()->sentence(),
            'status' => SubcontractorInvoice::STATUS_PENDING,
            'bill_id' => null,
            'converted_to_bill_at' => null,
            'submitted_by' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubcontractorInvoice::STATUS_PENDING,
        ]);
    }

    /**
     * Approved status.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubcontractorInvoice::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    /**
     * Rejected status.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubcontractorInvoice::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejection_reason' => 'Dokumen tidak lengkap',
        ]);
    }

    /**
     * Paid status.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubcontractorInvoice::STATUS_PAID,
            'approved_at' => now()->subDay(),
        ]);
    }

    /**
     * For specific work order.
     */
    public function forWorkOrder(SubcontractorWorkOrder $workOrder): static
    {
        return $this->state(fn (array $attributes) => [
            'subcontractor_work_order_id' => $workOrder->id,
            'subcontractor_id' => $workOrder->subcontractor_id,
        ]);
    }

    /**
     * For specific subcontractor.
     */
    public function forSubcontractor(Contact $subcontractor): static
    {
        return $this->state(fn (array $attributes) => [
            'subcontractor_id' => $subcontractor->id,
        ]);
    }

    /**
     * With submitter.
     */
    public function withSubmitter(?User $user = null): static
    {
        return $this->state(function (array $attributes) use ($user) {
            $u = $user ?? User::factory()->create();

            return [
                'submitted_by' => $u->id,
            ];
        });
    }

    /**
     * With approver.
     */
    public function withApprover(?User $user = null): static
    {
        return $this->state(function (array $attributes) use ($user) {
            $u = $user ?? User::factory()->create();

            return [
                'approved_by' => $u->id,
                'approved_at' => now(),
                'status' => SubcontractorInvoice::STATUS_APPROVED,
            ];
        });
    }

    /**
     * With specific amount.
     */
    public function withAmount(int $grossAmount, int $retentionHeld = 0, int $otherDeductions = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'gross_amount' => $grossAmount,
            'retention_held' => $retentionHeld,
            'other_deductions' => $otherDeductions,
            'net_amount' => $grossAmount - $retentionHeld - $otherDeductions,
        ]);
    }

    /**
     * Without retention.
     */
    public function withoutRetention(): static
    {
        return $this->state(function (array $attributes) {
            $grossAmount = $attributes['gross_amount'] ?? 10000000;

            return [
                'retention_held' => 0,
                'net_amount' => $grossAmount - ($attributes['other_deductions'] ?? 0),
            ];
        });
    }

    /**
     * Converted to bill.
     */
    public function convertedToBill(?Bill $bill = null): static
    {
        return $this->state(function (array $attributes) use ($bill) {
            $b = $bill ?? Bill::factory()->create();

            return [
                'status' => SubcontractorInvoice::STATUS_APPROVED,
                'approved_at' => now()->subHour(),
                'bill_id' => $b->id,
                'converted_to_bill_at' => now(),
            ];
        });
    }

    /**
     * With dates.
     */
    public function withDates(\DateTimeInterface $invoiceDate, ?\DateTimeInterface $dueDate = null): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate ?? \Carbon\Carbon::parse($invoiceDate)->addDays(30),
        ]);
    }
}
