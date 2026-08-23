<?php

declare(strict_types=1);

use App\Models\ElectricalPanel\BomPanelMeta;
use App\Models\ElectricalPanel\SpecValidationRuleSet;
use App\Models\Manufacturing\Bom;
use App\Models\User;
use App\Services\ElectricalPanel\SpecValidationRuleSetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('SpecValidationRuleSet::boms relation', function () {
    it('resolves to the manufacturing Bom model, not a phantom class', function () {
        $ruleSet = SpecValidationRuleSet::factory()->create();

        expect(get_class($ruleSet->boms()->getRelated()))->toBe(Bom::class);
    });

    it('counts BOMs linked through electrical_panel_bom_meta', function () {
        $ruleSet = SpecValidationRuleSet::factory()->create();
        $bom = Bom::factory()->create();
        BomPanelMeta::sync($bom, $ruleSet->id);

        expect($ruleSet->boms()->count())->toBe(1)
            ->and($ruleSet->boms()->first()->id)->toBe($bom->id);
    });

    it('counts zero when no BOM references the rule set', function () {
        $ruleSet = SpecValidationRuleSet::factory()->create();
        Bom::factory()->create();

        expect($ruleSet->boms()->count())->toBe(0);
    });
});

describe('SpecValidationRuleSetService::delete', function () {
    it('deletes an unreferenced rule set instead of fataling', function () {
        $ruleSet = SpecValidationRuleSet::factory()->create();

        app(SpecValidationRuleSetService::class)->delete($ruleSet);

        expect(SpecValidationRuleSet::find($ruleSet->id))->toBeNull();
    });

    it('refuses to delete a rule set still used by a BOM', function () {
        $ruleSet = SpecValidationRuleSet::factory()->create();
        BomPanelMeta::sync(Bom::factory()->create(), $ruleSet->id);

        expect(fn () => app(SpecValidationRuleSetService::class)->delete($ruleSet))
            ->toThrow(\App\Exceptions\Domain\ValidationException::class);

        expect(SpecValidationRuleSet::find($ruleSet->id))->not->toBeNull();
    });
});

describe('DELETE /api/v1/spec-rule-sets/{id}', function () {
    it('returns a success response rather than a 500', function () {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $ruleSet = SpecValidationRuleSet::factory()->create();

        $this->deleteJson("/api/v1/spec-rule-sets/{$ruleSet->id}")
            ->assertSuccessful();
    });
});
