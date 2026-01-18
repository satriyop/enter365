<?php

declare(strict_types=1);

namespace App\Domain\Sales\Events;

use App\Models\Sales\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePosted
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Invoice $invoice,
        public int $postedBy,
        public \Carbon\Carbon $postedAt
    ) {}
}
