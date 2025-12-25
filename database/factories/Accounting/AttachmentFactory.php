<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Attachment;
use App\Models\Accounting\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        $filename = $this->faker->uuid() . '.pdf';

        return [
            'attachable_type' => Invoice::class,
            'attachable_id' => Invoice::factory(),
            'filename' => $this->faker->word() . '.pdf',
            'disk' => 'local',
            'path' => 'attachments/' . $filename,
            'mime_type' => 'application/pdf',
            'size' => $this->faker->numberBetween(10000, 5000000),
            'description' => $this->faker->optional()->sentence(),
            'category' => Attachment::CATEGORY_INVOICE_PDF,
            'uploaded_by' => User::factory(),
        ];
    }

    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'filename' => $this->faker->word() . '.pdf',
            'mime_type' => 'application/pdf',
            'category' => Attachment::CATEGORY_INVOICE_PDF,
        ]);
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'filename' => $this->faker->word() . '.jpg',
            'mime_type' => 'image/jpeg',
            'category' => Attachment::CATEGORY_RECEIPT,
        ]);
    }

    public function receipt(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => Attachment::CATEGORY_RECEIPT,
        ]);
    }

    public function contract(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => Attachment::CATEGORY_CONTRACT,
        ]);
    }

    public function bankStatement(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => Attachment::CATEGORY_BANK_STATEMENT,
        ]);
    }

    public function taxDocument(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => Attachment::CATEGORY_TAX_DOCUMENT,
        ]);
    }

    public function forModel($model): static
    {
        return $this->state(fn (array $attributes) => [
            'attachable_type' => $model::class,
            'attachable_id' => $model->id,
        ]);
    }

    public function uploadedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'uploaded_by' => $user->id,
        ]);
    }
}
