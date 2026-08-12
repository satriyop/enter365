<?php

declare(strict_types=1);

use App\Contracts\Manufacturing\BomTemplateServiceInterface;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(BomTemplateServiceInterface::class);
});

describe('addItem', function () {
    it('creates item with auto sort_order', function () {
        $template = BomTemplate::factory()->create();
        BomTemplateItem::factory()->create(['template_id' => $template->id, 'sort_order' => 2]);

        $item = $this->service->addItem($template, [
            'type' => BomTemplateItem::TYPE_MATERIAL,
            'description' => 'New breaker',
            'default_quantity' => 4,
            'unit' => 'pcs',
        ]);

        expect($item)->toBeInstanceOf(BomTemplateItem::class)
            ->and($item->template_id)->toBe($template->id)
            ->and($item->description)->toBe('New breaker')
            ->and($item->sort_order)->toBe(3);
    });

    it('respects explicit sort_order', function () {
        $template = BomTemplate::factory()->create();

        $item = $this->service->addItem($template, [
            'type' => BomTemplateItem::TYPE_MATERIAL,
            'description' => 'First',
            'sort_order' => 10,
        ]);

        expect($item->sort_order)->toBe(10);
    });
});

describe('updateItem', function () {
    it('updates item fields', function () {
        $template = BomTemplate::factory()->create();
        $item = BomTemplateItem::factory()->create([
            'template_id' => $template->id,
            'description' => 'Old',
        ]);

        $updated = $this->service->updateItem($template, $item, [
            'description' => 'Updated line',
            'default_quantity' => 12,
        ]);

        expect($updated->description)->toBe('Updated line')
            ->and((float) $updated->default_quantity)->toEqual(12.0);
    });

    it('aborts when item belongs to another template', function () {
        $template = BomTemplate::factory()->create();
        $other = BomTemplate::factory()->create();
        $item = BomTemplateItem::factory()->create(['template_id' => $other->id]);

        $this->service->updateItem($template, $item, ['description' => 'Nope']);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

describe('deleteItem', function () {
    it('deletes item from template', function () {
        $template = BomTemplate::factory()->create();
        $item = BomTemplateItem::factory()->create(['template_id' => $template->id]);

        $this->service->deleteItem($template, $item);

        expect(BomTemplateItem::find($item->id))->toBeNull();
    });
});

describe('reorderItems', function () {
    it('updates sort_order by item id sequence', function () {
        $template = BomTemplate::factory()->create();
        $a = BomTemplateItem::factory()->create(['template_id' => $template->id, 'sort_order' => 0]);
        $b = BomTemplateItem::factory()->create(['template_id' => $template->id, 'sort_order' => 1]);
        $c = BomTemplateItem::factory()->create(['template_id' => $template->id, 'sort_order' => 2]);

        $this->service->reorderItems($template, [$c->id, $a->id, $b->id]);

        expect($c->fresh()->sort_order)->toBe(0)
            ->and($a->fresh()->sort_order)->toBe(1)
            ->and($b->fresh()->sort_order)->toBe(2);
    });
});

describe('toggleActive', function () {
    it('flips is_active flag', function () {
        $template = BomTemplate::factory()->create(['is_active' => true]);

        $toggled = $this->service->toggleActive($template);

        expect($toggled->is_active)->toBeFalse();

        $toggledAgain = $this->service->toggleActive($toggled);

        expect($toggledAgain->is_active)->toBeTrue();
    });
});

describe('product-linked items', function () {
    it('creates product-linked items', function () {
        $product = Product::factory()->create(['name' => 'MCB 16A']);
        $template = BomTemplate::factory()->create();

        $item = $this->service->addItem($template, [
            'type' => BomTemplateItem::TYPE_MATERIAL,
            'product_id' => $product->id,
            'description' => $product->name,
            'default_quantity' => 2,
        ]);

        expect($item->product_id)->toBe($product->id);
    });
});
