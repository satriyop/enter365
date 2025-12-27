<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAttachmentRequest;
use App\Http\Resources\Api\V1\AttachmentResource;
use App\Models\Accounting\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Attachment::query()->with('uploader');

        if ($request->has('attachable_type')) {
            $query->where('attachable_type', $request->input('attachable_type'));
        }

        if ($request->has('attachable_id')) {
            $query->where('attachable_id', $request->input('attachable_id'));
        }

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        $attachments = $query->orderByDesc('created_at')->paginate($request->input('per_page', 25));

        return AttachmentResource::collection($attachments);
    }

    public function store(StoreAttachmentRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $disk = config('filesystems.default', 'local');

        // Generate a unique path
        $folder = 'attachments/'.now()->format('Y/m');
        $filename = uniqid().'_'.$file->getClientOriginalName();
        $path = $file->storeAs($folder, $filename, $disk);

        $attachment = Attachment::create([
            'attachable_type' => $request->input('attachable_type'),
            'attachable_id' => $request->input('attachable_id'),
            'filename' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'description' => $request->input('description'),
            'category' => $request->input('category', Attachment::CATEGORY_OTHER),
            'uploaded_by' => auth()->id(),
        ]);

        return (new AttachmentResource($attachment->load('uploader')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Attachment $attachment): AttachmentResource
    {
        return new AttachmentResource($attachment->load(['uploader', 'attachable']));
    }

    public function destroy(Attachment $attachment): JsonResponse
    {
        $attachment->delete();

        return response()->json(['message' => 'Lampiran berhasil dihapus.']);
    }

    public function download(Attachment $attachment): StreamedResponse|JsonResponse
    {
        if (! $attachment->exists()) {
            return response()->json([
                'message' => 'File tidak ditemukan.',
            ], 404);
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->filename
        );
    }

    public function forModel(Request $request, string $type, int $id): AnonymousResourceCollection
    {
        $modelClass = $this->resolveModelClass($type);

        if (! $modelClass) {
            abort(404, 'Tipe model tidak valid.');
        }

        $attachments = Attachment::query()
            ->where('attachable_type', $modelClass)
            ->where('attachable_id', $id)
            ->with('uploader')
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 25));

        return AttachmentResource::collection($attachments);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'categories' => Attachment::getCategories(),
        ]);
    }

    protected function resolveModelClass(string $type): ?string
    {
        $models = [
            'invoice' => \App\Models\Accounting\Invoice::class,
            'bill' => \App\Models\Accounting\Bill::class,
            'payment' => \App\Models\Accounting\Payment::class,
            'journal-entry' => \App\Models\Accounting\JournalEntry::class,
            'contact' => \App\Models\Accounting\Contact::class,
        ];

        return $models[$type] ?? null;
    }
}
