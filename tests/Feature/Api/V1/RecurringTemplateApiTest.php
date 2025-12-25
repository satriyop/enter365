<?php

use App\Models\Accounting\Contact;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\RecurringTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Recurring Template API', function () {

    it('can list all recurring templates', function () {
        RecurringTemplate::factory()->invoice()->count(3)->create();

        $response = $this->getJson('/api/v1/recurring-templates');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter templates by type', function () {
        RecurringTemplate::factory()->invoice()->count(2)->create();
        RecurringTemplate::factory()->bill()->count(3)->create();

        $response = $this->getJson('/api/v1/recurring-templates?type=invoice');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter templates by is_active', function () {
        RecurringTemplate::factory()->invoice()->count(2)->create(['is_active' => true]);
        RecurringTemplate::factory()->invoice()->inactive()->count(3)->create();

        $response = $this->getJson('/api/v1/recurring-templates?is_active=1');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can create a recurring template', function () {
        $customer = Contact::factory()->customer()->create();

        $response = $this->postJson('/api/v1/recurring-templates', [
            'name' => 'Monthly Subscription',
            'type' => 'invoice',
            'contact_id' => $customer->id,
            'frequency' => 'monthly',
            'interval' => 1,
            'start_date' => now()->addWeek()->toDateString(),
            'tax_rate' => 11,
            'payment_term_days' => 30,
            'items' => [
                [
                    'description' => 'Monthly Service Fee',
                    'quantity' => 1,
                    'unit' => 'bulan',
                    'unit_price' => 1000000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Monthly Subscription')
            ->assertJsonPath('data.frequency', 'monthly')
            ->assertJsonPath('data.is_active', true);
    });

    it('validates required fields when creating template', function () {
        $response = $this->postJson('/api/v1/recurring-templates', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'type', 'contact_id', 'frequency', 'start_date', 'items']);
    });

    it('can show a recurring template', function () {
        $template = RecurringTemplate::factory()->invoice()->create();

        $response = $this->getJson("/api/v1/recurring-templates/{$template->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $template->id);
    });

    it('can update a recurring template', function () {
        $template = RecurringTemplate::factory()->invoice()->create();

        $response = $this->putJson("/api/v1/recurring-templates/{$template->id}", [
            'name' => 'Updated Template Name',
            'frequency' => 'quarterly',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Template Name')
            ->assertJsonPath('data.frequency', 'quarterly');
    });

    it('can pause a recurring template', function () {
        $template = RecurringTemplate::factory()->invoice()->create(['is_active' => true]);

        $response = $this->postJson("/api/v1/recurring-templates/{$template->id}/pause");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);
    });

    it('cannot pause already inactive template', function () {
        $template = RecurringTemplate::factory()->invoice()->inactive()->create();

        $response = $this->postJson("/api/v1/recurring-templates/{$template->id}/pause");

        $response->assertUnprocessable();
    });

    it('can resume a paused template', function () {
        $template = RecurringTemplate::factory()->invoice()->inactive()->create([
            'end_date' => null,
            'occurrences_limit' => null,
        ]);

        $response = $this->postJson("/api/v1/recurring-templates/{$template->id}/resume");

        $response->assertOk()
            ->assertJsonPath('data.is_active', true);
    });

    it('cannot resume template that reached occurrence limit', function () {
        $template = RecurringTemplate::factory()->invoice()->inactive()->create([
            'occurrences_limit' => 5,
            'occurrences_count' => 5,
        ]);

        $response = $this->postJson("/api/v1/recurring-templates/{$template->id}/resume");

        $response->assertUnprocessable();
    });

    it('can generate document from template', function () {
        $template = RecurringTemplate::factory()->invoice()->dueToGenerate()->create();

        $response = $this->postJson("/api/v1/recurring-templates/{$template->id}/generate");

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'document_type',
                'document_id',
                'document_number',
            ]);

        // Verify invoice was created
        $this->assertDatabaseHas('invoices', [
            'recurring_template_id' => $template->id,
        ]);
    });

    it('cannot generate from inactive template', function () {
        $template = RecurringTemplate::factory()->invoice()->inactive()->create();

        $response = $this->postJson("/api/v1/recurring-templates/{$template->id}/generate");

        $response->assertUnprocessable();
    });

    it('can create recurring template from invoice', function () {
        $invoice = Invoice::factory()->create();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/make-recurring", [
            'frequency' => 'monthly',
            'start_date' => now()->addMonth()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'invoice')
            ->assertJsonPath('data.frequency', 'monthly');
    });
});
