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
use App\Support\AddonExtensions;
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
            ->with(AddonExtensions::eagerLoads('bom_template.list', ['items', 'creator']));

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

        return (new BomTemplateResource($template->load(
            AddonExtensions::eagerLoads('bom_template.show', ['items', 'creator'])
        )))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display a template with its items.
     */
    public function show(BomTemplate $bomTemplate): BomTemplateResource
    {
        return new BomTemplateResource(
            $bomTemplate->load(AddonExtensions::eagerLoads('bom_template.show', [
                'items.product',
                'creator',
            ]))
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
        [$data, $addon] = AddonExtensions::splitValidated('bom_template_item', $request->validated());

        $item = $this->templateService->addItem($bomTemplate, $data, $addon);

        $with = AddonExtensions::eagerLoads('bom_template.item', ['product']);

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
        [$data, $addon] = AddonExtensions::splitValidated('bom_template_item', $request->validated());

        $item = $this->templateService->updateItem($bomTemplate, $item, $data, $addon);

        $with = AddonExtensions::eagerLoads('bom_template.item', ['product']);

        return new BomTemplateItemResource($item->load($with));
    }

    /**
     * Delete an item from a template.
     */
    public function destroyItem(
        BomTemplate $bomTemplate,
        BomTemplateItem $item
    ): JsonResponse {
        $this->templateService->deleteItem($bomTemplate, $item);

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

        $template = $this->templateService->reorderItems(
            $bomTemplate,
            array_map('intval', $request->input('item_ids'))
        );

        return response()->json([
            'message' => 'Urutan item berhasil diubah.',
            'data' => new BomTemplateResource($template->loadMissing('items')),
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

        $with = AddonExtensions::eagerLoads('bom_template.duplicate', [
            'items.product',
            'creator',
        ]);

        return (new BomTemplateResource($newTemplate->load($with)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Toggle template active status.
     */
    public function toggleActive(BomTemplate $bomTemplate): JsonResponse
    {
        $template = $this->templateService->toggleActive($bomTemplate);

        return response()->json([
            'message' => $template->is_active
                ? 'Template berhasil diaktifkan.'
                : 'Template berhasil dinonaktifkan.',
            'data' => new BomTemplateResource($template),
        ]);
    }

    /**
     * Preview creating a BOM from a template.
     */
    public function previewCreateBom(
        Request $request,
        BomTemplate $bomTemplate
    ): JsonResponse {
        $validated = $request->validate(array_merge([
            'quantity_overrides' => ['nullable', 'array'],
        ], AddonExtensions::validationRules('preview_bom_from_template')));

        $preview = $this->templateService->previewCreateFromTemplate($bomTemplate, $validated);

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
