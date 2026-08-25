<?php

declare(strict_types=1);

use App\Domain\Purchasing\Bills\Events\BillReceived;
use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Quotations\Events\QuotationApproved;
use App\Domain\Sales\Quotations\Events\QuotationSubmitted;
use App\Domain\Sales\Quotations\Events\QuotationWon;
use App\Infrastructure\Listeners\Purchasing\NotifyAccountPayableOnBillReceived;
use App\Infrastructure\Listeners\Sales\NotifyCustomerOnInvoiceSent;
use App\Infrastructure\Listeners\Sales\NotifyCustomerOnQuotationApproved;
use App\Infrastructure\Listeners\Sales\NotifySalesTeamOnQuotationSubmitted;
use App\Infrastructure\Listeners\Sales\NotifySalesTeamOnQuotationWon;
use App\Models\Contacts\Contact;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Notifications\BillReceivedTeamNotification;
use App\Notifications\InvoiceSentNotification;
use App\Notifications\QuotationApprovedNotification;
use App\Notifications\QuotationSubmittedTeamNotification;
use App\Notifications\QuotationWonTeamNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'accounting.company.email' => 'team@enter365.test',
        'accounting.notifications.team_email' => 'team@enter365.test',
        'accounting.notifications.invoice_sent.enabled' => true,
        'accounting.notifications.quotation_approved.enabled' => true,
        'accounting.notifications.quotation_submitted.enabled' => true,
        'accounting.notifications.quotation_won.enabled' => true,
        'accounting.notifications.bill_received.enabled' => true,
    ]);

    Notification::fake();
});

describe('customer notifications', function () {
    it('sends InvoiceSentNotification to contact email', function () {
        $contact = Contact::factory()->create(['email' => 'buyer@example.com']);
        $invoice = Invoice::factory()->create(['contact_id' => $contact->id]);

        (new NotifyCustomerOnInvoiceSent)->handle(
            InvoiceSent::fromInvoice($invoice)
        );

        Notification::assertSentOnDemand(InvoiceSentNotification::class, function ($notification, $channels, $notifiable) use ($invoice) {
            return $notification->invoice->is($invoice)
                && ($notifiable->routes['mail'] ?? null) === 'buyer@example.com';
        });
    });

    it('skips invoice notification when contact has no email', function () {
        $contact = Contact::factory()->create(['email' => null]);
        $invoice = Invoice::factory()->create(['contact_id' => $contact->id]);

        (new NotifyCustomerOnInvoiceSent)->handle(
            InvoiceSent::fromInvoice($invoice)
        );

        Notification::assertNothingSent();
    });

    it('sends QuotationApprovedNotification to contact email', function () {
        $contact = Contact::factory()->create(['email' => 'buyer@example.com']);
        $quotation = Quotation::factory()->create(['contact_id' => $contact->id]);

        (new NotifyCustomerOnQuotationApproved)->handle(
            QuotationApproved::fromQuotation($quotation)
        );

        Notification::assertSentOnDemand(QuotationApprovedNotification::class);
    });
});

describe('team notifications', function () {
    it('notifies sales team when quotation is submitted', function () {
        $quotation = Quotation::factory()->create();

        (new NotifySalesTeamOnQuotationSubmitted)->handle(
            QuotationSubmitted::fromQuotation($quotation)
        );

        Notification::assertSentOnDemand(QuotationSubmittedTeamNotification::class, function ($notification, $channels, $notifiable) {
            return ($notifiable->routes['mail'] ?? null) === 'team@enter365.test';
        });
    });

    it('notifies sales team when quotation is won', function () {
        $quotation = Quotation::factory()->create();

        (new NotifySalesTeamOnQuotationWon)->handle(
            QuotationWon::fromQuotation($quotation)
        );

        Notification::assertSentOnDemand(QuotationWonTeamNotification::class);
    });

    it('notifies AP team when bill is received', function () {
        $bill = Bill::factory()->create();

        (new NotifyAccountPayableOnBillReceived)->handle(
            BillReceived::fromBill($bill)
        );

        Notification::assertSentOnDemand(BillReceivedTeamNotification::class);
    });

    it('respects enabled=false config', function () {
        config(['accounting.notifications.invoice_sent.enabled' => false]);

        $contact = Contact::factory()->create(['email' => 'buyer@example.com']);
        $invoice = Invoice::factory()->create(['contact_id' => $contact->id]);

        (new NotifyCustomerOnInvoiceSent)->handle(
            InvoiceSent::fromInvoice($invoice)
        );

        Notification::assertNothingSent();
    });

    it('dispatches InvoiceSent through Laravel events to the mail listener', function () {
        $contact = Contact::factory()->create(['email' => 'buyer@example.com']);
        $invoice = Invoice::factory()->create(['contact_id' => $contact->id]);

        app(\App\Contracts\Events\EventDispatcherInterface::class)
            ->dispatch(InvoiceSent::fromInvoice($invoice));

        Notification::assertSentOnDemand(InvoiceSentNotification::class, function ($notification, $channels, $notifiable) use ($invoice) {
            return $notification->invoice->is($invoice)
                && ($notifiable->routes['mail'] ?? null) === 'buyer@example.com';
        });
    });
});
