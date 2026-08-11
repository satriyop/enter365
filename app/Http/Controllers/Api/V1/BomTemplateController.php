<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Manufacturing\BomTemplateServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateBomFromTemplateRequest;
use App\Http\Requests\Api\V1\StoreBomTemplateItemRequest;
use App\Http\Requests\Api\V1\StoreBomTemplateRequest;
use App\Http\Requests\Api\V1\UpdateBomTemplateItemRequest;
use App\Http\Requests\Api\V1\UpdateBomTemplateRequest;
use App\Http\Resources\Api\V1\BomResource;
use App\Http\Resources\Api\V1\BomTemplateItemResource;
use App\Http\Resources\Api\V1\BomTemplateResource;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class BomTemplateController extends Controller
{
    public function __construct(
        private BomTemplateServiceInterface $templateService
    ) {}

    /**
     * List all templates with filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = BomTemplate::query()
            ->with(['items', 'defaultRuleSet', 'creator']);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(code) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"]);
            });
        }

        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $templates = $query->paginate($request->input('per_page', 25));

        return BomTemplateResource::collection($templates);
    }

    /**
     * Create a new template.
     */
    public function store(StoreBomTemplateRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_path'] = $request->file('thumbnail')
                ->store('bom-templates', 'public');
        }

        $template = $this->templateService->createTemplate($data);

        return (new BomTemplateResource($template->load(['items', 'defaultRuleSet', 'creator'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display a template with its items.
     */
    public function show(BomTemplate $bomTemplate): BomTemplateResource
    {
        return new BomTemplateResource(
            $bomTemplate->load([
                'items.product',
                'items.panelMeta.componentStandard',
                'panelMeta.defaultRuleSet',
                'defaultRuleSet',
                'creator',
            ])
        );
    }

    /**
     * Update a template.
     */
    public function update(
        UpdateBomTemplateRequest $request,
        BomTemplate $bomTemplate
    ): BomTemplateResource {
        $data = $request->validated();

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail
            if ($bomTemplate->thumbnail_path) {
                Storage::disk('public')->delete($bomTemplate->thumbnail_path);
            }

            $data['thumbnail_path'] = $request->file('thumbnail')
                ->store('bom-templates', 'public');
        }

        $updatedTemplate = $this->templateService->updateTemplate($bomTemplate, $data);

        return new BomTemplateResource($updatedTemplate);
    }

    /**
     * Delete a template.
     */
    public function destroy(BomTemplate $bomTemplate): JsonResponse
    {
        // Delete thumbnail if exists
        if ($bomTemplate->thumbnail_path) {
            Storage::disk('public')->delete($bomTemplate->thumbnail_path);
        }

        $this->templateService->deleteTemplate($bomTemplate);

        return response()->json(['message' => 'Template berhasil dihapus.']);
    }

    /**
     * Get metadata for creating templates.
     *
     * @response array{data: array{categories: array<string, string>, item_types: array<string, string>}}
     */
    public function metadata(): JsonResponse
    {
        return response()->json([
            'data' => [
                'categories' => BomTemplate::getCategories(),
                'item_types' => BomTemplateItem::getTypes(),
            ],
        ]);
    }

    /**
     * Add an item to a template.
     */
    public function storeItem(
        StoreBomTemplateItemRequest $request,
        BomTemplate $bomTemplate
    ): JsonResponse {
        // Get max sort order and add 1 if no sort_order provided
        $data = $request->validated();
        $standardId = $data['component_standard_id'] ?? null;
        unset($data['component_standard_id']);

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = $bomTemplate->items()->max('sort_order') + 1;
        }

        $item = $bomTemplate->items()->create($data);

        if ($standardId !== null) {
            $this->templateService->syncTemplateItemStandard($item, (int) $standardId);
        }

        $with = ['product'];
        if (\App\Support\Features::enabled('electrical_panel')) {
            $with[] = 'panelMeta.componentStandard';
        }

        return (new BomTemplateItemResource($item->load($with)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an item within a template.
     */
    public function updateItem(
        UpdateBomTemplateItemRequest $request,
        BomTemplate $bomTemplate,
        BomTemplateItem $item
    ): BomTemplateItemResource {
        // Ensure the item belongs to the template
        if ($item->template_id !== $bomTemplate->id) {
            abort(404, 'Item tidak ditemukan dalam template ini.');
        }

        $data = $request->validated();
        $standardId = array_key_exists('component_standard_id', $data)
            ? $data['component_standard_id']
            : false;
        unset($data['component_standard_id']);

        $item->update($data);

        if ($standardId !== false) {
            $this->templateService->syncTemplateItemStandard(
                $item,
                $standardId !== null ? (int) $standardId : null
            );
        }

        $with = ['product'];
        if (\App\Support\Features::enabled('electrical_panel')) {
            $with[] = 'panelMeta.componentStandard';
        }

        return new BomTemplateItemResource($item->fresh($with));
    }

    /**
     * Delete an item from a template.
     */
    public function destroyItem(
        BomTemplate $bomTemplate,
        BomTemplateItem $item
    ): JsonResponse {
        // Ensure the item belongs to the template
        if ($item->template_id !== $bomTemplate->id) {
            abort(404, 'Item tidak ditemukan dalam template ini.');
        }

        $item->delete();

        return response()->json(['message' => 'Item berhasil dihapus.']);
    }

    /**
     * Reorder items within a template.
     *
     * @response array{message: string, data: BomTemplateResource}
     */
    public function reorderItems(
        Request $request,
        BomTemplate $bomTemplate
    ): JsonResponse {
        $request->validate([
            'item_ids' => ['required', 'array'],
            'item_ids.*' => ['required', 'integer', 'exists:bom_template_items,id'],
        ]);

        foreach ($request->input('item_ids') as $index => $itemId) {
            BomTemplateItem::where('id', $itemId)
                ->where('template_id', $bomTemplate->id)
                ->update(['sort_order' => $index]);
        }

        return response()->json([
            'message' => 'Urutan item berhasil diubah.',
            'data' => new BomTemplateResource($bomTemplate->fresh('items')),
        ]);
    }

    /**
     * Duplicate a template.
     */
    public function duplicate(
        Request $request,
        BomTemplate $bomTemplate
    ): JsonResponse {
        $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:bom_templates,code'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $thumbnailPath = $bomTemplate->thumbnail_path;
        if ($bomTemplate->thumbnail_path && Storage::disk('public')->exists($bomTemplate->thumbnail_path)) {
            $extension = pathinfo($bomTemplate->thumbnail_path, PATHINFO_EXTENSION);
            $newPath = 'bom-templates/'.uniqid().'.'.$extension;
            Storage::disk('public')->copy($bomTemplate->thumbnail_path, $newPath);
            $thumbnailPath = $newPath;
        }

        $newTemplate = $this->templateService->duplicateTemplate($bomTemplate, [
            'code' => $request->input('code'),
            'name' => $request->input('name'),
            'thumbnail_path' => $thumbnailPath,
        ]);

        $with = ['items.product', 'creator'];
        if (\App\Support\Features::enabled('electrical_panel')) {
            $with = [
                'items.panelMeta.componentStandard',
                'items.product',
                'panelMeta.defaultRuleSet',
                'defaultRuleSet',
                'creator',
            ];
        }

        return (new BomTemplateResource($newTemplate->load($with)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Toggle template active status.
     */
    public function toggleActive(BomTemplate $bomTemplate): JsonResponse
    {
        $bomTemplate->update(['is_active' => ! $bomTemplate->is_active]);

        return response()->json([
            'message' => $bomTemplate->is_active
                ? 'Template berhasil diaktifkan.'
                : 'Template berhasil dinonaktifkan.',
            'data' => new BomTemplateResource($bomTemplate->fresh()),
        ]);
    }

    /**
     * Get available brands for a template.
     *
     * @response array{data: array<array{code: string, name: string, coverage: int, coverage_percent: float}>, meta: array{template_id: int, template_code: string, items_with_standard: int}}
     */
    public function availableBrands(BomTemplate $bomTemplate): JsonResponse
    {
        $brands = $this->templateService->getAvailableBrandsForTemplate($bomTemplate);

        return response()->json([
            'data' => $brands,
            'meta' => [
                'template_id' => $bomTemplate->id,
                'template_code' => $bomTemplate->code,
                'items_with_standard' => $bomTemplate->items()
                    ->whereHas('panelMeta', fn ($q) => $q->whereNotNull('component_standard_id'))
                    ->count(),
            ],
        ]);
    }

    /**
     * Preview creating a BOM from a template.
     *
     * @response array{data: array<array{template_item_id: int, type: string, description: string, quantity: float, unit: string, unit_cost: int, product: array<mixed>|null, component_standard: array<mixed>|null, status: string, notes: string|null, is_required: bool, is_quantity_variable: bool}>, report: array<mixed>}
     */
    public function previewCreateBom(
        Request $request,
        BomTemplate $bomTemplate
    ): JsonResponse {
        $request->validate([
            'target_brand' => ['nullable', 'string'],
            'quantity_overrides' => ['nullable', 'array'],
        ]);

        $preview = $this->templateService->previewCreateFromTemplate($bomTemplate, [
            'target_brand' => $request->input('target_brand'),
            'quantity_overrides' => $request->input('quantity_overrides', []),
        ]);

        return response()->json([
            'data' => $preview['items'],
            'report' => $preview['report'],
        ]);
    }

    /**
     * Create a BOM from a template.
     *
     * @response array{message: string, data: BomResource, report: array<mixed>}
     */
    public function createBom(
        CreateBomFromTemplateRequest $request,
        BomTemplate $bomTemplate
    ): JsonResponse {
        $result = $this->templateService->createBomFromTemplate(
            $bomTemplate,
            $request->validated()
        );

        return response()->json([
            'message' => 'BOM berhasil dibuat dari template.',
            'data' => new BomResource($result['bom']),
            'report' => $result['report'],
        ], 201);
    }
}
