<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\JournalEntryFilter;
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

    public function index(JournalEntryFilter $filter): AnonymousResourceCollection
    {
        $entries = JournalEntry::query()
            ->withCount(['lines'])
            ->withSum('lines as total_debit', 'debit')
            ->withSum('lines as total_credit', 'credit')
            ->filter($filter)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate($filter->getRequest()->input('per_page', 25));

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

    public function show(JournalEntry $journalEntry, JournalEntryFilter $filter): JournalEntryResource
    {
        $filter->apply($journalEntry->newQuery());

        $journalEntry->loadMissing(['lines.account', 'fiscalPeriod', 'reversedBy', 'reversalOf']);

        return new JournalEntryResource($journalEntry);
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
