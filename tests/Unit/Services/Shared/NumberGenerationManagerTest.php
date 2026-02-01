<?php

declare(strict_types=1);

use App\Contracts\Shared\NumberGenerationStrategy;
use App\Services\Shared\NumberGenerationManager;

describe('register', function () {
    it('registers a strategy', function () {
        $manager = new NumberGenerationManager;

        $strategy = Mockery::mock(NumberGenerationStrategy::class);
        $strategy->shouldReceive('getName')->andReturn('sequential');

        $result = $manager->register($strategy);

        expect($result)->toBe($manager)
            ->and($manager->hasStrategy('sequential'))->toBeTrue();
    });

    it('registers multiple strategies', function () {
        $manager = new NumberGenerationManager;

        $sequential = Mockery::mock(NumberGenerationStrategy::class);
        $sequential->shouldReceive('getName')->andReturn('sequential');

        $projectBased = Mockery::mock(NumberGenerationStrategy::class);
        $projectBased->shouldReceive('getName')->andReturn('project_based');

        $manager->register($sequential)->register($projectBased);

        expect($manager->getAvailableStrategies())->toBe(['sequential', 'project_based']);
    });
});

describe('setDefault', function () {
    it('returns self for fluent chaining', function () {
        $manager = new NumberGenerationManager;

        $result = $manager->setDefault('project_based');

        expect($result)->toBe($manager);
    });
});

describe('getStrategy', function () {
    it('returns registered strategy', function () {
        $manager = new NumberGenerationManager;

        $strategy = Mockery::mock(NumberGenerationStrategy::class);
        $strategy->shouldReceive('getName')->andReturn('sequential');

        $manager->register($strategy);

        expect($manager->getStrategy('sequential'))->toBe($strategy);
    });

    it('throws exception for unregistered strategy', function () {
        $manager = new NumberGenerationManager;

        expect(fn () => $manager->getStrategy('nonexistent'))
            ->toThrow(InvalidArgumentException::class, 'Unknown numbering strategy: nonexistent');
    });
});

describe('hasStrategy', function () {
    it('returns true for registered strategy', function () {
        $manager = new NumberGenerationManager;

        $strategy = Mockery::mock(NumberGenerationStrategy::class);
        $strategy->shouldReceive('getName')->andReturn('sequential');

        $manager->register($strategy);

        expect($manager->hasStrategy('sequential'))->toBeTrue();
    });

    it('returns false for unregistered strategy', function () {
        $manager = new NumberGenerationManager;

        expect($manager->hasStrategy('nonexistent'))->toBeFalse();
    });
});

describe('getAvailableStrategies', function () {
    it('returns empty array when no strategies registered', function () {
        $manager = new NumberGenerationManager;

        expect($manager->getAvailableStrategies())->toBe([]);
    });

    it('returns all registered strategy names', function () {
        $manager = new NumberGenerationManager;

        foreach (['sequential', 'project_based', 'random'] as $name) {
            $strategy = Mockery::mock(NumberGenerationStrategy::class);
            $strategy->shouldReceive('getName')->andReturn($name);
            $manager->register($strategy);
        }

        expect($manager->getAvailableStrategies())->toBe(['sequential', 'project_based', 'random']);
    });
});

describe('generate', function () {
    it('uses configured strategy from config', function () {
        $manager = new NumberGenerationManager;

        $strategy = Mockery::mock(NumberGenerationStrategy::class);
        $strategy->shouldReceive('getName')->andReturn('sequential');
        $strategy->shouldReceive('generateWithContext')
            ->with('INV-202601-', 'invoices', 'invoice_number', [])
            ->once()
            ->andReturn('INV-202601-0001');

        $manager->register($strategy);

        // Config returns default 'sequential' when key doesn't exist
        config(['documents.invoice.numbering' => 'sequential']);

        $result = $manager->generate('invoice', 'INV-202601-', 'invoices', 'invoice_number');

        expect($result)->toBe('INV-202601-0001');
    });

    it('falls back to default strategy when config not set', function () {
        $manager = new NumberGenerationManager;

        $strategy = Mockery::mock(NumberGenerationStrategy::class);
        $strategy->shouldReceive('getName')->andReturn('sequential');
        $strategy->shouldReceive('generateWithContext')
            ->once()
            ->andReturn('DOC-0001');

        $manager->register($strategy);

        // Use a document type that has no config entry at all
        $result = $manager->generate('totally_unconfigured_type_xyz', 'DOC-', 'documents', 'doc_number');

        expect($result)->toBe('DOC-0001');
    });

    it('passes context to strategy', function () {
        $manager = new NumberGenerationManager;

        $context = ['project_id' => 5];

        $strategy = Mockery::mock(NumberGenerationStrategy::class);
        $strategy->shouldReceive('getName')->andReturn('sequential');
        $strategy->shouldReceive('generateWithContext')
            ->with('PO-', 'purchase_orders', 'po_number', $context)
            ->once()
            ->andReturn('PO-0001');

        $manager->register($strategy);

        $result = $manager->generate('purchase_order', 'PO-', 'purchase_orders', 'po_number', $context);

        expect($result)->toBe('PO-0001');
    });

    it('throws exception when strategy not registered', function () {
        $manager = new NumberGenerationManager;

        config(['documents.invoice.numbering' => 'missing_strategy']);

        expect(fn () => $manager->generate('invoice', 'INV-', 'invoices', 'invoice_number'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('uses custom default strategy', function () {
        $manager = new NumberGenerationManager;

        $strategy = Mockery::mock(NumberGenerationStrategy::class);
        $strategy->shouldReceive('getName')->andReturn('project_based');
        $strategy->shouldReceive('generateWithContext')
            ->once()
            ->andReturn('PRJ-001-0001');

        $manager->register($strategy);
        $manager->setDefault('project_based');

        // With no config set, should use the custom default 'project_based'
        $result = $manager->generate('unconfigured_type_abc', 'PRJ-', 'projects', 'number');

        expect($result)->toBe('PRJ-001-0001');
    });
});
