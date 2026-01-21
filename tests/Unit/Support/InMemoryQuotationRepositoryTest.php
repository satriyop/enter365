<?php

declare(strict_types=1);

use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Models\Sales\Quotation;
use Tests\Support\InMemoryQuotationRepository;

beforeEach(function () {
    $this->repository = new InMemoryQuotationRepository;
});

describe('Base CRUD Operations', function () {

    test('creates quotation with auto-increment ID', function () {
        $quotation = $this->repository->create([
            'contact_id' => 1,
            'quotation_number' => 'QUO-001',
            'subject' => 'Test Quotation',
        ]);

        expect($quotation->id)->toBe(1);
        expect($quotation->contact_id)->toBe(1);
        expect($quotation->quotation_number)->toBe('QUO-001');
        expect($quotation->status)->toBe(DocumentStatus::Draft);
    });

    test('creates multiple quotations with sequential IDs', function () {
        $q1 = $this->repository->create(['contact_id' => 1]);
        $q2 = $this->repository->create(['contact_id' => 2]);
        $q3 = $this->repository->create(['contact_id' => 3]);

        expect($q1->id)->toBe(1);
        expect($q2->id)->toBe(2);
        expect($q3->id)->toBe(3);
    });

    test('finds quotation by ID', function () {
        $created = $this->repository->create(['contact_id' => 1]);

        $found = $this->repository->find($created->id);

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($created->id);
    });

    test('returns null for non-existent ID', function () {
        $found = $this->repository->find(999);

        expect($found)->toBeNull();
    });

    test('findOrFail throws exception for non-existent ID', function () {
        $this->repository->findOrFail(999);
    })->throws(EntityNotFoundException::class);

    test('updates quotation', function () {
        $quotation = $this->repository->create([
            'contact_id' => 1,
            'subject' => 'Original',
        ]);

        $updated = $this->repository->update($quotation, [
            'subject' => 'Updated',
        ]);

        expect($updated->subject)->toBe('Updated');
    });

    test('updates quotation by ID', function () {
        $quotation = $this->repository->create([
            'contact_id' => 1,
            'subject' => 'Original',
        ]);

        $updated = $this->repository->update($quotation->id, [
            'subject' => 'Updated',
        ]);

        expect($updated->subject)->toBe('Updated');
    });

    test('deletes quotation', function () {
        $quotation = $this->repository->create(['contact_id' => 1]);

        $result = $this->repository->delete($quotation);

        expect($result)->toBeTrue();
        expect($this->repository->find($quotation->id))->toBeNull();
    });

    test('delete returns false for non-existent quotation', function () {
        $result = $this->repository->delete(999);

        expect($result)->toBeFalse();
    });

    test('gets all quotations', function () {
        $this->repository->create(['contact_id' => 1]);
        $this->repository->create(['contact_id' => 2]);

        $all = $this->repository->all();

        expect($all)->toHaveCount(2);
    });

    test('counts quotations', function () {
        $this->repository->create(['contact_id' => 1]);
        $this->repository->create(['contact_id' => 2]);
        $this->repository->create(['contact_id' => 2]);

        expect($this->repository->count())->toBe(3);
        expect($this->repository->count(['contact_id' => 2]))->toBe(2);
    });

    test('checks existence', function () {
        $this->repository->create(['contact_id' => 1]);

        expect($this->repository->exists(['contact_id' => 1]))->toBeTrue();
        expect($this->repository->exists(['contact_id' => 999]))->toBeFalse();
    });

    test('finds by criteria', function () {
        $this->repository->create(['contact_id' => 1, 'subject' => 'A']);
        $this->repository->create(['contact_id' => 1, 'subject' => 'B']);
        $this->repository->create(['contact_id' => 2, 'subject' => 'C']);

        $found = $this->repository->findBy(['contact_id' => 1]);

        expect($found)->toHaveCount(2);
    });

    test('finds one by criteria', function () {
        $this->repository->create(['contact_id' => 1, 'quotation_number' => 'QUO-001']);
        $this->repository->create(['contact_id' => 1, 'quotation_number' => 'QUO-002']);

        $found = $this->repository->findOneBy(['quotation_number' => 'QUO-001']);

        expect($found)->not->toBeNull();
        expect($found->quotation_number)->toBe('QUO-001');
    });

    test('paginates quotations', function () {
        for ($i = 0; $i < 50; $i++) {
            $this->repository->create(['contact_id' => $i]);
        }

        $paginated = $this->repository->paginate(10);

        expect($paginated->count())->toBe(10);
        expect($paginated->total())->toBe(50);
    });

});

describe('Quotation-Specific Queries', function () {

    test('finds by quotation number', function () {
        $this->repository->create(['quotation_number' => 'QUO-001']);
        $this->repository->create(['quotation_number' => 'QUO-002']);

        $found = $this->repository->findByNumber('QUO-001');

        expect($found)->not->toBeNull();
        expect($found->quotation_number)->toBe('QUO-001');
    });

    test('finds by status', function () {
        $this->repository->create(['status' => DocumentStatus::Draft]);
        $this->repository->create(['status' => DocumentStatus::Draft]);
        $this->repository->create(['status' => DocumentStatus::Submitted]);

        $drafts = $this->repository->findByStatus(DocumentStatus::Draft);

        expect($drafts)->toHaveCount(2);
    });

    test('finds by contact', function () {
        $this->repository->create(['contact_id' => 1]);
        $this->repository->create(['contact_id' => 1]);
        $this->repository->create(['contact_id' => 2]);

        $found = $this->repository->findByContact(1);

        expect($found)->toHaveCount(2);
    });

    test('finds active quotations', function () {
        $this->repository->create(['status' => DocumentStatus::Draft]);
        $this->repository->create(['status' => DocumentStatus::Submitted]);
        $this->repository->create(['status' => DocumentStatus::Approved]);
        $this->repository->create(['status' => DocumentStatus::Cancelled]);
        $this->repository->create(['status' => DocumentStatus::Expired]);

        $active = $this->repository->findActive();

        expect($active)->toHaveCount(3);
    });

    test('finds pending approval', function () {
        $this->repository->create(['status' => DocumentStatus::Draft]);
        $this->repository->create(['status' => DocumentStatus::Submitted, 'submitted_at' => now()]);
        $this->repository->create(['status' => DocumentStatus::Submitted, 'submitted_at' => now()->subDay()]);

        $pending = $this->repository->findPendingApproval();

        expect($pending)->toHaveCount(2);
    });

    test('finds assigned to user', function () {
        $this->repository->create(['assigned_to' => 1]);
        $this->repository->create(['assigned_to' => 1]);
        $this->repository->create(['assigned_to' => 2]);

        $assigned = $this->repository->findAssignedTo(1);

        expect($assigned)->toHaveCount(2);
    });

    test('finds by outcome', function () {
        $this->repository->create(['outcome' => 'won']);
        $this->repository->create(['outcome' => 'won']);
        $this->repository->create(['outcome' => 'lost']);
        $this->repository->create(['outcome' => null]);

        expect($this->repository->findByOutcome('won'))->toHaveCount(2);
        expect($this->repository->findByOutcome('lost'))->toHaveCount(1);
        expect($this->repository->findByOutcome(null))->toHaveCount(1);
    });

    test('finds needing follow-up', function () {
        $this->repository->create(['next_follow_up_at' => now()->subDay()]);
        $this->repository->create(['next_follow_up_at' => now()->addDay()]);
        $this->repository->create(['next_follow_up_at' => null]);

        $needsFollowUp = $this->repository->findNeedingFollowUp();

        expect($needsFollowUp)->toHaveCount(1);
    });

    test('finds expiring soon', function () {
        $this->repository->create([
            'status' => DocumentStatus::Draft,
            'valid_until' => now()->addDays(3),
        ]);
        $this->repository->create([
            'status' => DocumentStatus::Submitted,
            'valid_until' => now()->addDays(5),
        ]);
        $this->repository->create([
            'status' => DocumentStatus::Approved,
            'valid_until' => now()->addDays(10), // Outside 7-day window
        ]);
        $this->repository->create([
            'status' => DocumentStatus::Cancelled, // Inactive status
            'valid_until' => now()->addDays(3),
        ]);

        $expiringSoon = $this->repository->findExpiringSoon(7);

        expect($expiringSoon)->toHaveCount(2);
    });

    test('finds by date range', function () {
        $this->repository->create([
            'quotation_date' => now()->subDays(5)->toDateString(),
        ]);
        $this->repository->create([
            'quotation_date' => now()->subDays(2)->toDateString(),
        ]);
        $this->repository->create([
            'quotation_date' => now()->subDays(10)->toDateString(), // Outside range
        ]);

        $range = DateRange::of(
            now()->subDays(7)->toDateString(),
            now()->toDateString()
        );
        $found = $this->repository->findByDateRange($range);

        expect($found)->toHaveCount(2);
    });

});

describe('Statistics', function () {

    test('gets value by status', function () {
        $this->repository->create(['status' => DocumentStatus::Draft, 'total' => 100000]);
        $this->repository->create(['status' => DocumentStatus::Draft, 'total' => 200000]);
        $this->repository->create(['status' => DocumentStatus::Approved, 'total' => 500000]);

        $stats = $this->repository->getValueByStatus();

        expect($stats)->toBeArray();

        $draftStats = collect($stats)->firstWhere('status', DocumentStatus::Draft);
        expect($draftStats['count'])->toBe(2);
        expect($draftStats['total_value'])->toBe(300000);
    });

    test('gets win rate stats', function () {
        $this->repository->create(['status' => DocumentStatus::Approved, 'outcome' => 'won']);
        $this->repository->create(['status' => DocumentStatus::Approved, 'outcome' => 'won']);
        $this->repository->create(['status' => DocumentStatus::Approved, 'outcome' => 'lost']);
        $this->repository->create(['status' => DocumentStatus::Approved, 'outcome' => null]);
        $this->repository->create(['status' => DocumentStatus::Draft, 'outcome' => null]);

        $stats = $this->repository->getWinRateStats();

        expect($stats['total'])->toBe(4);
        expect($stats['won'])->toBe(2);
        expect($stats['lost'])->toBe(1);
        expect($stats['pending'])->toBe(1);
        expect($stats['win_rate'])->toBe(50.0);
    });

});

describe('Repository Management', function () {

    test('resets repository', function () {
        $this->repository->create(['contact_id' => 1]);
        $this->repository->create(['contact_id' => 2]);

        $this->repository->reset();

        expect($this->repository->count())->toBe(0);

        $new = $this->repository->create(['contact_id' => 3]);
        expect($new->id)->toBe(1); // ID resets
    });

    test('seeds with existing quotations', function () {
        $q1 = new Quotation(['contact_id' => 1]);
        $q1->id = 100;
        $q2 = new Quotation(['contact_id' => 2]);
        $q2->id = 200;

        $this->repository->seed([$q1, $q2]);

        expect($this->repository->count())->toBe(2);
        expect($this->repository->find(100))->not->toBeNull();
        expect($this->repository->find(200))->not->toBeNull();

        // Next auto-increment should be after highest seeded ID
        $new = $this->repository->create(['contact_id' => 3]);
        expect($new->id)->toBe(201);
    });

    test('provides collection for assertions', function () {
        $this->repository->create(['contact_id' => 1]);
        $this->repository->create(['contact_id' => 2]);

        $collection = $this->repository->getCollection();

        expect($collection)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($collection)->toHaveCount(2);
    });

});
