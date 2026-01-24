<?php

declare(strict_types=1);

use App\Domain\Shared\DocumentNumbers;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('DocumentNumbers', function () {
    it('generates first number when no records exist', function () {
        $number = DocumentNumbers::generate('INV-202501-', 'invoices', 'invoice_number');

        expect($number)->toBe('INV-202501-0001');
    });

    it('generates next number based on last record', function () {
        // Create a test invoice using factory
        Invoice::factory()->create([
            'invoice_number' => 'INV-202501-0005',
        ]);

        $number = DocumentNumbers::generate('INV-202501-', 'invoices', 'invoice_number');

        expect($number)->toBe('INV-202501-0006');
    });

    it('handles different prefix formats', function () {
        $number = DocumentNumbers::generate('PO-202501-', 'purchase_orders', 'po_number');

        expect($number)->toBe('PO-202501-0001');
    });

    it('works with existing records of different prefixes', function () {
        // Create invoice with different prefix
        Invoice::factory()->create([
            'invoice_number' => 'INV-202401-0010',
        ]);

        // Generate PO number (should start from 0001)
        $number = DocumentNumbers::generate('PO-202501-', 'purchase_orders', 'po_number');

        expect($number)->toBe('PO-202501-0001');
    });
});
