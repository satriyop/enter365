<?php

declare(strict_types=1);

use App\Contracts\Repositories\Sales\QuotationRepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Infrastructure\Repositories\Sales\EloquentQuotationRepository;
use App\Models\Contacts\Contact;
use App\Models\Sales\Quotation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(QuotationRepositoryInterface::class);
});

test('repository is bound to eloquent implementation', function () {
    expect($this->repository)->toBeInstanceOf(EloquentQuotationRepository::class);
});

test('find returns quotation by id', function () {
    $quotation = Quotation::factory()->create();

    $found = $this->repository->find($quotation->id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($quotation->id);
});

test('find returns null for non-existent id', function () {
    $found = $this->repository->find(999999);

    expect($found)->toBeNull();
});

test('findOrFail throws exception for non-existent id', function () {
    $this->repository->findOrFail(999999);
})->throws(EntityNotFoundException::class);

test('findByNumber returns quotation by number', function () {
    $quotation = Quotation::factory()->create([
        'quotation_number' => 'QUO-2024-001',
    ]);

    $found = $this->repository->findByNumber('QUO-2024-001');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($quotation->id);
});

test('findByStatus returns quotations with given status', function () {
    Quotation::factory()->count(3)->draft()->create();
    Quotation::factory()->count(2)->submitted()->create();

    $drafts = $this->repository->findByStatus(DocumentStatus::Draft);

    expect($drafts)->toHaveCount(3)
        ->each(fn ($quotation) => $quotation->status->toBe(DocumentStatus::Draft));
});

test('findByContact returns quotations for contact', function () {
    $contact = Contact::factory()->create();
    Quotation::factory()->count(3)->forContact($contact)->create();
    Quotation::factory()->count(2)->create(); // Different contacts

    $quotations = $this->repository->findByContact($contact->id);

    expect($quotations)->toHaveCount(3)
        ->each(fn ($quotation) => $quotation->contact_id->toBe($contact->id));
});

test('findByDateRange returns quotations within range', function () {
    $startDate = Carbon::now()->subDays(10);
    $endDate = Carbon::now();

    // Quotation within range
    Quotation::factory()->create([
        'quotation_date' => Carbon::now()->subDays(5),
    ]);

    // Quotation before range
    Quotation::factory()->create([
        'quotation_date' => Carbon::now()->subDays(20),
    ]);

    // Quotation after range
    Quotation::factory()->create([
        'quotation_date' => Carbon::now()->addDays(5),
    ]);

    $range = DateRange::of($startDate->toDateString(), $endDate->toDateString());
    $quotations = $this->repository->findByDateRange($range);

    expect($quotations)->toHaveCount(1);
});

test('findExpiringSoon returns quotations expiring within days', function () {
    // Quotation expiring in 3 days
    Quotation::factory()->create([
        'status' => DocumentStatus::Approved,
        'valid_until' => Carbon::now()->addDays(3),
    ]);

    // Quotation expiring in 10 days (outside default 7 days)
    Quotation::factory()->create([
        'status' => DocumentStatus::Approved,
        'valid_until' => Carbon::now()->addDays(10),
    ]);

    // Already expired quotation
    Quotation::factory()->create([
        'status' => DocumentStatus::Approved,
        'valid_until' => Carbon::now()->subDays(1),
    ]);

    $expiring = $this->repository->findExpiringSoon(7);

    expect($expiring)->toHaveCount(1);
});

test('findPendingApproval returns submitted quotations', function () {
    Quotation::factory()->count(2)->submitted()->create();
    Quotation::factory()->count(3)->draft()->create();
    Quotation::factory()->count(1)->approved()->create();

    $pending = $this->repository->findPendingApproval();

    expect($pending)->toHaveCount(2)
        ->each(fn ($quotation) => $quotation->status->toBe(DocumentStatus::Submitted));
});

test('findNeedingFollowUp returns quotations with past follow-up dates', function () {
    // Quotation needing follow-up
    Quotation::factory()->create([
        'status' => DocumentStatus::Approved,
        'next_follow_up_at' => Carbon::now()->subDays(1),
    ]);

    // Quotation with future follow-up
    Quotation::factory()->create([
        'status' => DocumentStatus::Approved,
        'next_follow_up_at' => Carbon::now()->addDays(3),
    ]);

    // Quotation without follow-up
    Quotation::factory()->create([
        'status' => DocumentStatus::Approved,
        'next_follow_up_at' => null,
    ]);

    $needsFollowUp = $this->repository->findNeedingFollowUp();

    expect($needsFollowUp)->toHaveCount(1);
});

test('findAssignedTo returns quotations assigned to user', function () {
    $user = User::factory()->create();

    Quotation::factory()->count(2)->assignedTo($user)->create();
    Quotation::factory()->count(3)->create(); // Different users

    $assigned = $this->repository->findAssignedTo($user->id);

    expect($assigned)->toHaveCount(2)
        ->each(fn ($quotation) => $quotation->assigned_to->toBe($user->id));
});

test('findActive returns draft, submitted, and approved quotations', function () {
    Quotation::factory()->count(2)->draft()->create();
    Quotation::factory()->count(1)->submitted()->create();
    Quotation::factory()->count(1)->approved()->create();
    Quotation::factory()->count(1)->expired()->create();
    Quotation::factory()->count(1)->converted()->create();

    $active = $this->repository->findActive();

    expect($active)->toHaveCount(4);
});

test('findByOutcome returns quotations with specific outcome', function () {
    Quotation::factory()->won()->create();
    Quotation::factory()->won()->create();
    Quotation::factory()->lost()->create();
    Quotation::factory()->approved()->create(); // No outcome

    $won = $this->repository->findByOutcome('won');
    $lost = $this->repository->findByOutcome('lost');
    $pending = $this->repository->findByOutcome(null);

    expect($won)->toHaveCount(2)
        ->and($lost)->toHaveCount(1)
        ->and($pending)->toHaveCount(1);
});

test('findWithRelations loads all relations', function () {
    $quotation = Quotation::factory()->create();

    $found = $this->repository->findWithRelations($quotation->id);

    expect($found)->not->toBeNull()
        ->and($found->relationLoaded('contact'))->toBeTrue()
        ->and($found->relationLoaded('items'))->toBeTrue()
        ->and($found->relationLoaded('creator'))->toBeTrue();
});

test('getValueByStatus returns totals grouped by status', function () {
    Quotation::factory()->count(2)->draft()->create(['total' => 100000]);
    Quotation::factory()->count(1)->approved()->create(['total' => 200000]);

    $stats = $this->repository->getValueByStatus();

    expect($stats)->toBeArray();

    $draftStats = collect($stats)->firstWhere('status', DocumentStatus::Draft);
    expect($draftStats['count'])->toBe(2)
        ->and($draftStats['total_value'])->toBe(200000);

    $approvedStats = collect($stats)->firstWhere('status', DocumentStatus::Approved);
    expect($approvedStats['count'])->toBe(1)
        ->and($approvedStats['total_value'])->toBe(200000);
});

test('getWinRateStats returns correct statistics', function () {
    // Won quotations
    Quotation::factory()->count(3)->won()->create();
    // Lost quotations
    Quotation::factory()->count(2)->lost()->create();
    // Approved without outcome
    Quotation::factory()->count(1)->approved()->create();

    $stats = $this->repository->getWinRateStats();

    expect($stats['won'])->toBe(3)
        ->and($stats['lost'])->toBe(2)
        ->and($stats['win_rate'])->toBe(60.0); // 3 / (3+2) = 60%
});

test('create persists new quotation', function () {
    $contact = Contact::factory()->create();
    $user = User::factory()->create();

    $quotation = $this->repository->create([
        'contact_id' => $contact->id,
        'quotation_number' => 'QUO-TEST-001',
        'quotation_date' => now(),
        'valid_until' => now()->addDays(30),
        'status' => DocumentStatus::Draft,
        'subtotal' => 100000,
        'tax_amount' => 11000,
        'total' => 111000,
        'created_by' => $user->id,
    ]);

    expect($quotation->id)->not->toBeNull()
        ->and($quotation->quotation_number)->toBe('QUO-TEST-001');

    $this->assertDatabaseHas('quotations', [
        'quotation_number' => 'QUO-TEST-001',
    ]);
});

test('update modifies quotation', function () {
    $quotation = Quotation::factory()->draft()->create();

    $updated = $this->repository->update($quotation->id, [
        'subject' => 'Updated Subject',
    ]);

    expect($updated->subject)->toBe('Updated Subject');

    $this->assertDatabaseHas('quotations', [
        'id' => $quotation->id,
        'subject' => 'Updated Subject',
    ]);
});

test('delete soft deletes quotation', function () {
    $quotation = Quotation::factory()->create();
    $id = $quotation->id;

    $result = $this->repository->delete($id);

    expect($result)->toBeTrue();
    $this->assertSoftDeleted('quotations', ['id' => $id]);
});

test('count returns total number of quotations', function () {
    Quotation::factory()->count(5)->create();

    $count = $this->repository->count();

    expect($count)->toBe(5);
});

test('exists returns true when matching record exists', function () {
    Quotation::factory()->create([
        'quotation_number' => 'QUO-EXISTS-001',
    ]);

    $exists = $this->repository->exists(['quotation_number' => 'QUO-EXISTS-001']);

    expect($exists)->toBeTrue();
});

test('exists returns false when no matching record', function () {
    $exists = $this->repository->exists(['quotation_number' => 'QUO-NONEXISTENT']);

    expect($exists)->toBeFalse();
});
