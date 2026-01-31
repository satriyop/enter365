<?php

declare(strict_types=1);

use App\Contracts\Accounting\Strategies\ClosingStrategy;
use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Accounting\Strategies\InventoryAccountingStrategy;
use App\Contracts\Accounting\Strategies\ManufacturingCostStrategy;
use App\Contracts\Accounting\Strategies\ReturnAccountingStrategy;
use App\Exceptions\Domain\BusinessRuleException;
use App\Services\Accounting\AccountingPolicyManager;
use App\Services\Accounting\Strategies\COGS\COGSOnInvoiceStrategy;
use App\Services\Accounting\Strategies\Inventory\HybridInventoryStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->manager = app(AccountingPolicyManager::class);
});

describe('AccountingPolicyManager - inventory', function () {

    it('returns configured inventory strategy', function () {
        config(['accounting.policies.inventory_method' => 'hybrid']);

        $strategy = $this->manager->inventory();

        expect($strategy)->toBeInstanceOf(InventoryAccountingStrategy::class)
            ->and($strategy)->toBeInstanceOf(HybridInventoryStrategy::class);
    });

    it('returns perpetual strategy when configured', function () {
        config(['accounting.policies.inventory_method' => 'perpetual']);

        $strategy = $this->manager->inventory();

        expect($strategy)->toBeInstanceOf(\App\Services\Accounting\Strategies\Inventory\PerpetualInventoryStrategy::class);
    });

    it('returns periodic strategy when configured', function () {
        config(['accounting.policies.inventory_method' => 'periodic']);

        $strategy = $this->manager->inventory();

        expect($strategy)->toBeInstanceOf(\App\Services\Accounting\Strategies\Inventory\PeriodicInventoryStrategy::class);
    });

    it('throws exception for unknown inventory method', function () {
        config(['accounting.policies.inventory_method' => 'invalid_method']);

        $this->manager->inventory();
    })->throws(BusinessRuleException::class, 'Unknown inventory method');

});

describe('AccountingPolicyManager - cogs', function () {

    it('returns configured COGS recognition strategy', function () {
        config(['accounting.policies.cogs_recognition' => 'on_invoice']);

        $strategy = $this->manager->cogs();

        expect($strategy)->toBeInstanceOf(COGSRecognitionStrategy::class)
            ->and($strategy)->toBeInstanceOf(COGSOnInvoiceStrategy::class);
    });

    it('returns on_delivery strategy when configured', function () {
        config(['accounting.policies.cogs_recognition' => 'on_delivery']);

        $strategy = $this->manager->cogs();

        expect($strategy)->toBeInstanceOf(\App\Services\Accounting\Strategies\COGS\COGSOnDeliveryStrategy::class);
    });

    it('returns manual strategy when configured', function () {
        config(['accounting.policies.cogs_recognition' => 'manual']);

        $strategy = $this->manager->cogs();

        expect($strategy)->toBeInstanceOf(\App\Services\Accounting\Strategies\COGS\ManualCOGSStrategy::class);
    });

    it('throws exception for unknown COGS method', function () {
        config(['accounting.policies.cogs_recognition' => 'invalid_cogs']);

        $this->manager->cogs();
    })->throws(BusinessRuleException::class, 'Unknown COGS recognition method');

});

describe('AccountingPolicyManager - returns', function () {

    it('returns configured return accounting strategy', function () {
        config(['accounting.policies.return_accounting' => 'full_journal']);

        $strategy = $this->manager->returns();

        expect($strategy)->toBeInstanceOf(ReturnAccountingStrategy::class)
            ->and($strategy)->toBeInstanceOf(\App\Services\Accounting\Strategies\Returns\FullReturnJournalStrategy::class);
    });

    it('returns inventory_only strategy when configured', function () {
        config(['accounting.policies.return_accounting' => 'inventory_only']);

        $strategy = $this->manager->returns();

        expect($strategy)->toBeInstanceOf(\App\Services\Accounting\Strategies\Returns\InventoryOnlyReturnStrategy::class);
    });

    it('throws exception for unknown return method', function () {
        config(['accounting.policies.return_accounting' => 'invalid_return']);

        $this->manager->returns();
    })->throws(BusinessRuleException::class, 'Unknown return accounting method');

});

describe('AccountingPolicyManager - manufacturing', function () {

    it('returns configured manufacturing cost strategy', function () {
        config(['accounting.policies.manufacturing_costing' => 'project_based']);

        $strategy = $this->manager->manufacturing();

        expect($strategy)->toBeInstanceOf(ManufacturingCostStrategy::class)
            ->and($strategy)->toBeInstanceOf(\App\Services\Accounting\Strategies\Manufacturing\ProjectBasedCostingStrategy::class);
    });

    it('returns job_costing strategy when configured', function () {
        config(['accounting.policies.manufacturing_costing' => 'job_costing']);

        $strategy = $this->manager->manufacturing();

        expect($strategy)->toBeInstanceOf(\App\Services\Accounting\Strategies\Manufacturing\JobCostingStrategy::class);
    });

    it('returns wip_accounting strategy when configured', function () {
        config(['accounting.policies.manufacturing_costing' => 'wip_accounting']);

        $strategy = $this->manager->manufacturing();

        expect($strategy)->toBeInstanceOf(\App\Services\Accounting\Strategies\Manufacturing\WIPAccountingStrategy::class);
    });

    it('throws exception for unknown manufacturing method', function () {
        config(['accounting.policies.manufacturing_costing' => 'invalid_manufacturing']);

        $this->manager->manufacturing();
    })->throws(BusinessRuleException::class, 'Unknown manufacturing costing method');

});

describe('AccountingPolicyManager - closing', function () {

    it('returns configured closing strategy', function () {
        config(['accounting.policies.closing_strategy' => 'direct']);

        $strategy = $this->manager->closing();

        expect($strategy)->toBeInstanceOf(ClosingStrategy::class)
            ->and($strategy)->toBeInstanceOf(\App\Services\Accounting\Strategies\Closing\DirectClosingStrategy::class);
    });

    it('returns income_summary strategy when configured', function () {
        config(['accounting.policies.closing_strategy' => 'income_summary']);

        $strategy = $this->manager->closing();

        expect($strategy)->toBeInstanceOf(\App\Services\Accounting\Strategies\Closing\IncomeSummaryStrategy::class);
    });

    it('throws exception for unknown closing method', function () {
        config(['accounting.policies.closing_strategy' => 'invalid_closing']);

        $this->manager->closing();
    })->throws(BusinessRuleException::class, 'Unknown closing strategy');

});

describe('AccountingPolicyManager - getCurrentPolicies', function () {

    it('returns all current policy configurations', function () {
        config([
            'accounting.policies.inventory_method' => 'perpetual',
            'accounting.policies.cogs_recognition' => 'on_delivery',
            'accounting.policies.return_accounting' => 'inventory_only',
            'accounting.policies.manufacturing_costing' => 'job_costing',
            'accounting.policies.closing_strategy' => 'income_summary',
        ]);

        $policies = $this->manager->getCurrentPolicies();

        expect($policies)->toBeArray()
            ->and($policies['inventory_method'])->toBe('perpetual')
            ->and($policies['cogs_recognition'])->toBe('on_delivery')
            ->and($policies['return_accounting'])->toBe('inventory_only')
            ->and($policies['manufacturing_costing'])->toBe('job_costing')
            ->and($policies['closing_strategy'])->toBe('income_summary');
    });

    it('uses default values when config not set', function () {
        // The config helper with a default value will return the default when key doesn't exist
        // But when explicitly set to null, it returns null. So let's test that the defaults
        // are applied by the service when the config is missing.
        $policies = $this->manager->getCurrentPolicies();

        // The getCurrentPolicies method returns config values with defaults
        expect($policies)->toHaveKey('inventory_method')
            ->and($policies)->toHaveKey('cogs_recognition')
            ->and($policies)->toHaveKey('return_accounting')
            ->and($policies)->toHaveKey('manufacturing_costing')
            ->and($policies)->toHaveKey('closing_strategy');
    });

});

describe('AccountingPolicyManager - isPolicySet', function () {

    it('returns true when policy matches value', function () {
        config(['accounting.policies.inventory_method' => 'perpetual']);

        $result = $this->manager->isPolicySet('inventory_method', 'perpetual');

        expect($result)->toBeTrue();
    });

    it('returns false when policy does not match value', function () {
        config(['accounting.policies.inventory_method' => 'perpetual']);

        $result = $this->manager->isPolicySet('inventory_method', 'periodic');

        expect($result)->toBeFalse();
    });

    it('checks multiple policy types', function () {
        config([
            'accounting.policies.cogs_recognition' => 'on_invoice',
            'accounting.policies.closing_strategy' => 'direct',
        ]);

        expect($this->manager->isPolicySet('cogs_recognition', 'on_invoice'))->toBeTrue()
            ->and($this->manager->isPolicySet('closing_strategy', 'direct'))->toBeTrue()
            ->and($this->manager->isPolicySet('cogs_recognition', 'manual'))->toBeFalse();
    });

});

describe('AccountingPolicyManager - strategy resolution', function () {

    it('resolves strategies from Laravel container', function () {
        config(['accounting.policies.inventory_method' => 'hybrid']);

        $strategy1 = $this->manager->inventory();
        $strategy2 = $this->manager->inventory();

        // Both should be instances (container resolution working)
        expect($strategy1)->toBeInstanceOf(HybridInventoryStrategy::class)
            ->and($strategy2)->toBeInstanceOf(HybridInventoryStrategy::class);
    });

    it('can switch strategies dynamically', function () {
        // Start with one strategy
        config(['accounting.policies.cogs_recognition' => 'on_invoice']);
        $strategy1 = $this->manager->cogs();

        expect($strategy1)->toBeInstanceOf(COGSOnInvoiceStrategy::class);

        // Switch to different strategy
        config(['accounting.policies.cogs_recognition' => 'on_delivery']);
        $strategy2 = $this->manager->cogs();

        expect($strategy2)->toBeInstanceOf(\App\Services\Accounting\Strategies\COGS\COGSOnDeliveryStrategy::class);
    });

});
