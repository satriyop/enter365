<?php

declare(strict_types=1);

use App\Domain\Shared\Approval\AutoApproveStrategy;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('AutoApproveStrategy', function () {
    beforeEach(function () {
        $this->strategy = new AutoApproveStrategy;
    });

    it('never requires approval', function () {
        $invoice = Invoice::factory()->create([
            'total_amount' => 1_000_000_000, // 1 billion
        ]);

        expect($this->strategy->requiresApproval($invoice))->toBeFalse();
    });

    it('returns empty approvers list', function () {
        $invoice = Invoice::factory()->create();

        expect($this->strategy->getApprovers($invoice))->toBeEmpty();
    });

    it('allows anyone to approve', function () {
        $invoice = Invoice::factory()->create();

        expect($this->strategy->canApprove($invoice, 1))->toBeTrue()
            ->and($this->strategy->canApprove($invoice, 999))->toBeTrue();
    });

    it('has correct name', function () {
        expect($this->strategy->getName())->toBe('auto_approve');
    });
});
