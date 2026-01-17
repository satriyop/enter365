<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Services\Accounting\JournalServiceInterface;
use App\Contracts\Services\Domain\DocumentNumberGeneratorInterface;
use App\Contracts\Services\Domain\InvoiceCalculatorInterface;
use App\Events\Sales\InvoicePosted;
use App\Listeners\Sales\PostInvoiceToJournal;
use App\Services\Accounting\JournalService;
use App\Services\Domain\DatabaseBackedNumberGenerator;
use App\Services\Domain\InvoiceCalculator;
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
        $this->app->bind(DocumentNumberGeneratorInterface::class, DatabaseBackedNumberGenerator::class);
        $this->app->bind(InvoiceCalculatorInterface::class, InvoiceCalculator::class);
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
