<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Sales\InvoiceCalculatorInterface;
use App\Contracts\Sales\QuotationNumberGeneratorInterface;
use App\Domain\Sales\Events\InvoicePosted;
use App\Domain\Sales\Invoices\InvoiceCalculator;
use App\Domain\Sales\Quotations\QuotationNumberGenerator;
use App\Listeners\Sales\PostInvoiceToJournal;
use App\Services\Accounting\JournalService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AccountingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(JournalServiceInterface::class, JournalService::class);
        $this->app->bind(InvoiceCalculatorInterface::class, InvoiceCalculator::class);
        $this->app->bind(QuotationNumberGeneratorInterface::class, QuotationNumberGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            InvoicePosted::class,
            PostInvoiceToJournal::class
        );
    }
}
