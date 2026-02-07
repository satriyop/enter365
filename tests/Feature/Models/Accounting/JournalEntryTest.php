<?php

declare(strict_types=1);

use App\Exceptions\Domain\DocumentLockedException;
use App\Models\Accounting\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('JournalEntry - Deletion Guard', function () {

    it('prevents deletion of posted journal entries', function () {
        $entry = JournalEntry::factory()->create(['is_posted' => true]);

        $entry->delete();
    })->throws(DocumentLockedException::class, 'Jurnal yang sudah diposting tidak dapat dihapus');

    it('allows deletion of unposted journal entries', function () {
        $entry = JournalEntry::factory()->create(['is_posted' => false]);

        $entry->delete();

        expect(JournalEntry::find($entry->id))->toBeNull();
    });

});
