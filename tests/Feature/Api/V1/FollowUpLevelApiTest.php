<?php

use App\Models\Accounting\FollowUpLevel;
use App\Models\Sales\Invoice;
use App\Services\Sales\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('Follow-up levels (#153)', function () {
    it('lists seeded dunning levels', function () {
        $this->getJson('/api/v1/follow-up-levels')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Upcoming')
            ->assertJsonPath('meta.total', 5);
    });

    it('creates updates and deletes a follow-up level', function () {
        $create = $this->postJson('/api/v1/follow-up-levels', [
            'name' => 'Legal notice',
            'delay_days' => 45,
            'sequence' => 60,
            'send_email' => false,
            'message' => 'Somasi.',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Legal notice')
            ->assertJsonPath('data.delay_days', 45)
            ->assertJsonPath('data.send_email', false);

        $id = $create->json('data.id');
        $this->putJson('/api/v1/follow-up-levels/'.$id, [
            'delay_days' => 60,
        ])->assertOk()->assertJsonPath('data.delay_days', 60);

        $this->deleteJson('/api/v1/follow-up-levels/'.$id)->assertOk();
        expect(FollowUpLevel::query()->whereKey($id)->exists())->toBeFalse();
    });

    it('validates required fields', function () {
        $this->postJson('/api/v1/follow-up-levels', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'delay_days']);
    });

    it('schedules invoice reminders from active follow-up levels', function () {
        FollowUpLevel::query()->update(['is_active' => false]);
        FollowUpLevel::factory()->create(['name' => 'Plus ten', 'delay_days' => 10, 'is_active' => true]);
        FollowUpLevel::factory()->create(['name' => 'Plus forty', 'delay_days' => 40, 'is_active' => true]);

        $invoice = Invoice::factory()->sent()->create([
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $reminders = app(ReminderService::class)->scheduleInvoiceReminders($invoice);

        expect($reminders)->toHaveCount(2)
            ->and($reminders->pluck('days_offset')->sort()->values()->all())->toBe([10, 40])
            ->and($reminders->firstWhere('days_offset', 40)?->type)->toBe('final_notice');
    });
});
