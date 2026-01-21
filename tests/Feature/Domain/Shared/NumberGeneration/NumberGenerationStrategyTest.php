<?php

declare(strict_types=1);

use App\Domain\Shared\NumberGeneration\SequentialNumberStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('SequentialNumberStrategy', function () {
    beforeEach(function () {
        $this->strategy = new SequentialNumberStrategy;
    });

    it('generates first number when no records exist', function () {
        $number = $this->strategy->generate('INV-202501-', 'invoices', 'invoice_number');

        expect($number)->toBe('INV-202501-0001');
    });

    it('generates next number based on last record', function () {
        // Create a test invoice using factory
        \App\Models\Sales\Invoice::factory()->create([
            'invoice_number' => 'INV-202501-0005',
        ]);

        $number = $this->strategy->generate('INV-202501-', 'invoices', 'invoice_number');

        expect($number)->toBe('INV-202501-0006');
    });

    it('supports context-based pad length', function () {
        $number = $this->strategy->generateWithContext(
            'WO-202501-',
            'work_orders',
            'wo_number',
            ['pad_length' => 5]
        );

        expect($number)->toBe('WO-202501-00001');
    });

    it('has correct name', function () {
        expect($this->strategy->getName())->toBe('sequential');
    });
});
