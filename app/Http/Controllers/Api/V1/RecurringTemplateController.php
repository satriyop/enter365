<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRecurringTemplateRequest;
use App\Http\Requests\Api\V1\UpdateRecurringTemplateRequest;
use App\Http\Resources\Api\V1\RecurringTemplateResource;
use App\Models\Accounting\RecurringTemplate;
use App\Services\Accounting\RecurringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecurringTemplateController extends Controller
{
    public function __construct(
        private RecurringService $recurringService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = RecurringTemplate::query()->with('contact');

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('frequency')) {
            $query->where('frequency', $request->input('frequency'));
        }

        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->input('contact_id'));
        }

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"]);
            });
        }

        $templates = $query->orderByDesc('created_at')->paginate($request->input('per_page', 25));

        return RecurringTemplateResource::collection($templates);
    }

    public function store(StoreRecurringTemplateRequest $request): JsonResponse
    {
        $template = RecurringTemplate::create([
            ...$request->validated(),
            'next_generate_date' => $request->start_date,
            'occurrences_count' => 0,
            'is_active' => true,
            'auto_post' => $request->boolean('auto_post', false),
            'auto_send' => $request->boolean('auto_send', false),
            'created_by' => auth()->id(),
        ]);

        return (new RecurringTemplateResource($template->load('contact')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(RecurringTemplate $recurringTemplate): RecurringTemplateResource
    {
        return new RecurringTemplateResource(
            $recurringTemplate->load(['contact', 'invoices', 'bills'])
        );
    }

    public function update(UpdateRecurringTemplateRequest $request, RecurringTemplate $recurringTemplate): RecurringTemplateResource
    {
        $recurringTemplate->update($request->validated());

        return new RecurringTemplateResource($recurringTemplate->fresh('contact'));
    }

    public function destroy(RecurringTemplate $recurringTemplate): JsonResponse
    {
        // Check if template has generated documents
        $hasDocuments = $recurringTemplate->invoices()->exists() || $recurringTemplate->bills()->exists();

        if ($hasDocuments) {
            // Soft delete by deactivating
            $recurringTemplate->update(['is_active' => false]);
            $recurringTemplate->delete();

            return response()->json([
                'message' => 'Template dinonaktifkan karena sudah memiliki dokumen yang dihasilkan.',
            ]);
        }

        $recurringTemplate->forceDelete();

        return response()->json(['message' => 'Template berhasil dihapus.']);
    }

    public function generate(RecurringTemplate $recurringTemplate): JsonResponse
    {
        if (! $recurringTemplate->is_active) {
            return response()->json([
                'message' => 'Template tidak aktif.',
            ], 422);
        }

        $document = $this->recurringService->generateFromTemplate($recurringTemplate);

        if (! $document) {
            return response()->json([
                'message' => 'Belum waktunya untuk menghasilkan dokumen dari template ini.',
            ], 422);
        }

        $type = $recurringTemplate->type === RecurringTemplate::TYPE_INVOICE ? 'Faktur' : 'Tagihan';

        return response()->json([
            'message' => "{$type} berhasil dihasilkan dari template.",
            'document_type' => $recurringTemplate->type,
            'document_id' => $document->id,
            'document_number' => $document->{$recurringTemplate->type.'_number'},
        ]);
    }

    public function pause(RecurringTemplate $recurringTemplate): JsonResponse
    {
        if (! $recurringTemplate->is_active) {
            return response()->json([
                'message' => 'Template sudah tidak aktif.',
            ], 422);
        }

        $recurringTemplate->update(['is_active' => false]);

        return response()->json([
            'message' => 'Template berhasil dijeda.',
            'data' => new RecurringTemplateResource($recurringTemplate->fresh()),
        ]);
    }

    public function resume(RecurringTemplate $recurringTemplate): JsonResponse
    {
        if ($recurringTemplate->is_active) {
            return response()->json([
                'message' => 'Template sudah aktif.',
            ], 422);
        }

        // Check if template has reached its limit
        if ($recurringTemplate->occurrences_limit && $recurringTemplate->occurrences_count >= $recurringTemplate->occurrences_limit) {
            return response()->json([
                'message' => 'Template sudah mencapai batas maksimal pengulangan.',
            ], 422);
        }

        // Check if end date has passed
        if ($recurringTemplate->end_date && $recurringTemplate->end_date->isPast()) {
            return response()->json([
                'message' => 'Tanggal akhir template sudah terlewat.',
            ], 422);
        }

        $recurringTemplate->update(['is_active' => true]);

        return response()->json([
            'message' => 'Template berhasil diaktifkan kembali.',
            'data' => new RecurringTemplateResource($recurringTemplate->fresh()),
        ]);
    }
}
