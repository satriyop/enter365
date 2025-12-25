<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreJournalEntryRequest;
use App\Http\Resources\Api\V1\JournalEntryResource;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class JournalEntryController extends Controller
{
    public function __construct(
        private JournalService $journalService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = JournalEntry::query()->with(['lines.account']);

        if ($request->has('is_posted')) {
            $query->where('is_posted', $request->boolean('is_posted'));
        }

        if ($request->has('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }

        if ($request->has('start_date')) {
            $query->where('entry_date', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->where('entry_date', '<=', $request->input('end_date'));
        }

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(entry_number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(reference) LIKE ?', ["%{$search}%"]);
            });
        }

        $entries = $query->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 25));

        return JournalEntryResource::collection($entries);
    }

    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        $entry = $this->journalService->createEntry(
            $request->validated(),
            $request->boolean('auto_post')
        );

        return (new JournalEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }

    public function show(JournalEntry $journalEntry): JournalEntryResource
    {
        return new JournalEntryResource(
            $journalEntry->load(['lines.account', 'fiscalPeriod', 'reversedBy', 'reversalOf'])
        );
    }

    public function post(JournalEntry $journalEntry): JournalEntryResource|JsonResponse
    {
        try {
            $entry = $this->journalService->postEntry($journalEntry);

            return new JournalEntryResource($entry->load(['lines.account']));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function reverse(JournalEntry $journalEntry, Request $request): JournalEntryResource|JsonResponse
    {
        try {
            $reversalEntry = $this->journalService->reverseEntry(
                $journalEntry,
                $request->input('description')
            );

            return new JournalEntryResource($reversalEntry);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
