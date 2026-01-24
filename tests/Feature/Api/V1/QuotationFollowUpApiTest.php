<?php

use App\Domain\Sales\Quotations\Enums\QuotationOutcome;
use App\Models\Contacts\Contact;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Quotation Follow-Up List', function () {

    it('can list quotations needing follow-up', function () {
        $customer = Contact::factory()->customer()->create();

        // Quotations with overdue follow-up
        Quotation::factory()
            ->forContact($customer)
            ->submitted()
            ->overdueFollowUp()
            ->count(5)
            ->create();

        $this->assertMaxQueries(15, function () {
            $response = $this->getJson('/api/v1/quotation-follow-up?needs_follow_up=1');
            $response->assertOk();
        });

        $response = $this->getJson('/api/v1/quotation-follow-up?needs_follow_up=1');
        $response->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'status' => ['value', 'label', 'color'],
                        'priority' => ['value', 'label'],
                        'needs_follow_up',
                    ]
                ]
            ]);
    });

    it('can filter by assigned user', function () {
        $customer = Contact::factory()->customer()->create();
        $assignedUser = User::factory()->create();

        Quotation::factory()
            ->forContact($customer)
            ->submitted()
            ->assignedTo($assignedUser)
            ->count(2)
            ->create();

        Quotation::factory()
            ->forContact($customer)
            ->submitted()
            ->count(3)
            ->create();

        $response = $this->getJson("/api/v1/quotation-follow-up?assigned_to={$assignedUser->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter high priority quotations', function () {
        $customer = Contact::factory()->customer()->create();

        Quotation::factory()
            ->forContact($customer)
            ->submitted()
            ->highPriority()
            ->count(2)
            ->create();

        Quotation::factory()
            ->forContact($customer)
            ->submitted()
            ->urgentPriority()
            ->create();

        Quotation::factory()
            ->forContact($customer)
            ->submitted()
            ->count(3)
            ->create();

        $response = $this->getJson('/api/v1/quotation-follow-up?high_priority_only=1');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

});

describe('Quotation Activities', function () {

    it('can list activities for a quotation', function () {
        $customer = Contact::factory()->customer()->create();
        $quotation = Quotation::factory()->forContact($customer)->submitted()->create();

        QuotationActivity::factory()
            ->forQuotation($quotation)
            ->byUser($this->user)
            ->count(5)
            ->create();

        $response = $this->getJson("/api/v1/quotations/{$quotation->id}/activities");

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('can record a call activity', function () {
        $customer = Contact::factory()->customer()->create();
        $quotation = Quotation::factory()->forContact($customer)->submitted()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/activities", [
            'type' => 'call',
            'contact_method' => 'phone',
            'subject' => 'Follow-up call',
            'description' => 'Discussed pricing details',
            'activity_at' => now()->toIso8601String(),
            'duration_minutes' => 15,
            'contact_person' => 'John Doe',
            'contact_phone' => '081234567890',
            'outcome' => 'positive',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.activity_type', 'call')
            ->assertJsonPath('data.outcome', 'positive')
            ->assertJsonPath('data.duration_minutes', 15);

        // Verify quotation was updated
        $quotation->refresh();
        expect($quotation->last_contacted_at)->not->toBeNull();
        expect($quotation->follow_up_count)->toBe(1);
    });

    it('can record activity with follow-up scheduling', function () {
        $customer = Contact::factory()->customer()->create();
        $quotation = Quotation::factory()->forContact($customer)->submitted()->create();

        $nextFollowUp = now()->addDays(3)->toIso8601String();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/activities", [
            'type' => 'call',
            'activity_at' => now()->toIso8601String(),
            'description' => 'Customer needs time to review',
            'next_follow_up_at' => $nextFollowUp,
            'follow_up_type' => 'call',
            'outcome' => 'neutral',
        ]);

        $response->assertCreated();

        // Verify quotation's follow-up was updated
        $quotation->refresh();
        expect($quotation->next_follow_up_at)->not->toBeNull();
    });

    it('validates activity type', function () {
        $customer = Contact::factory()->customer()->create();
        $quotation = Quotation::factory()->forContact($customer)->submitted()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/activities", [
            'type' => 'invalid_type',
            'activity_at' => now()->toIso8601String(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    });

});

describe('Schedule Follow-Up', function () {

    it('can schedule follow-up for a quotation', function () {
        $customer = Contact::factory()->customer()->create();
        $quotation = Quotation::factory()->forContact($customer)->submitted()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/schedule-follow-up", [
            'next_follow_up_at' => now()->addDays(5)->toIso8601String(),
            'notes' => 'Customer requested follow-up next week',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $quotation->id);

        $quotation->refresh();
        expect($quotation->next_follow_up_at)->not->toBeNull();
    });

    it('validates follow-up date is in future', function () {
        $customer = Contact::factory()->customer()->create();
        $quotation = Quotation::factory()->forContact($customer)->submitted()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/schedule-follow-up", [
            'next_follow_up_at' => now()->subDay()->toIso8601String(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['next_follow_up_at']);
    });

});

describe('Assign Quotation', function () {

    it('can assign quotation to a user', function () {
        $customer = Contact::factory()->customer()->create();
        $quotation = Quotation::factory()->forContact($customer)->submitted()->create();
        $salesPerson = User::factory()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/assign", [
            'assigned_to' => $salesPerson->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.assigned_to', $salesPerson->id);
    });

    it('validates user exists', function () {
        $customer = Contact::factory()->customer()->create();
        $quotation = Quotation::factory()->forContact($customer)->submitted()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/assign", [
            'assigned_to' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to']);
    });

});

describe('Update Priority', function () {

    it('can update quotation priority', function () {
        $customer = Contact::factory()->customer()->create();
        $quotation = Quotation::factory()->forContact($customer)->submitted()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/priority", [
            'priority' => 'high',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.priority.value', 'high');
    });

    it('validates priority value', function () {
        $customer = Contact::factory()->customer()->create();
        $quotation = Quotation::factory()->forContact($customer)->submitted()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/priority", [
            'priority' => 'invalid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['priority']);
    });

});

describe('Win/Loss Tracking', function () {

    it('can mark approved quotation as won', function () {
        $customer = Contact::factory()->customer()->create();
        $quotation = Quotation::factory()->forContact($customer)->approved()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/mark-won", [
            'won_reason' => 'harga_kompetitif',
            'outcome_notes' => 'Customer happy with our pricing',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.outcome.value', 'won')
            ->assertJsonPath('data.won_reason', 'harga_kompetitif');

        $quotation->refresh();
        expect($quotation->outcome)->toBe(QuotationOutcome::Won);
        expect($quotation->outcome_at)->not->toBeNull();
    });

    it('cannot mark non-approved quotation as won', function () {
        $customer = Contact::factory()->customer()->create();
        $quotation = Quotation::factory()->forContact($customer)->submitted()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/mark-won", [
            'won_reason' => 'harga_kompetitif',
        ]);

        $response->assertUnprocessable();
    });

    it('can mark quotation as lost', function () {
        $customer = Contact::factory()->customer()->create();
        $quotation = Quotation::factory()->forContact($customer)->approved()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/mark-lost", [
            'lost_reason' => 'harga_tinggi',
            'lost_to_competitor' => 'PT Competitor',
            'outcome_notes' => 'Competitor offered 20% discount',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.outcome.value', 'lost')
            ->assertJsonPath('data.lost_reason', 'harga_tinggi')
            ->assertJsonPath('data.lost_to_competitor', 'PT Competitor');

        $quotation->refresh();
        expect($quotation->outcome)->toBe(QuotationOutcome::Lost);
    });

    it('cannot mark already decided quotation', function () {
        $customer = Contact::factory()->customer()->create();
        $quotation = Quotation::factory()->forContact($customer)->won()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/mark-lost", [
            'lost_reason' => 'harga_tinggi',
        ]);

        $response->assertUnprocessable();
    });

});

describe('Statistics', function () {

    it('can get win/loss statistics', function () {
        $customer = Contact::factory()->customer()->create();
        $currentMonthDate = now()->startOfMonth()->addDays(5);

        // Won quotations
        Quotation::factory()
            ->forContact($customer)
            ->won()
            ->count(3)
            ->create(['quotation_date' => $currentMonthDate]);

        // Lost quotations
        Quotation::factory()
            ->forContact($customer)
            ->lost()
            ->count(2)
            ->create(['quotation_date' => $currentMonthDate]);

        // Pending quotations
        Quotation::factory()
            ->forContact($customer)
            ->approved()
            ->count(5)
            ->create(['quotation_date' => $currentMonthDate]);

        $response = $this->getJson('/api/v1/quotation-follow-up/statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'period' => ['start', 'end'],
                'counts' => ['total', 'won', 'lost', 'pending'],
                'values' => ['won', 'lost', 'pending'],
                'conversion_rate',
                'lost_reasons',
                'won_reasons',
            ]);

        expect($response->json('counts.won'))->toBe(3);
        expect($response->json('counts.lost'))->toBe(2);
        expect($response->json('conversion_rate'))->toEqual(60.0); // 3/(3+2) * 100
    });

    it('can get follow-up summary', function () {
        $customer = Contact::factory()->customer()->create();

        // Overdue follow-up
        Quotation::factory()
            ->forContact($customer)
            ->submitted()
            ->overdueFollowUp()
            ->count(2)
            ->create();

        // Today's follow-up
        Quotation::factory()
            ->forContact($customer)
            ->submitted()
            ->create([
                'next_follow_up_at' => now(),
            ]);

        $response = $this->getJson('/api/v1/quotation-follow-up/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'overdue',
                'today',
                'upcoming_week',
                'no_follow_up_scheduled',
            ]);

        expect($response->json('overdue'))->toBe(2);
    });

});
