<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\JournalFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreJournalRequest;
use App\Http\Requests\Api\V1\UpdateJournalRequest;
use App\Http\Resources\Api\V1\JournalResource;
use App\Models\Accounting\Journal;
use App\Services\Accounting\JournalMasterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JournalController extends Controller
{
    public function __construct(
        private JournalMasterService $journalMasterService
    ) {}

    public function index(JournalFilter $filter): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Journal::class);

        $journals = Journal::query()
            ->with(['defaultAccount', 'suspenseAccount', 'outstandingReceiptsAccount', 'outstandingPaymentsAccount'])
            ->filter($filter)
            ->orderBy('name')
            ->paginate($filter->getRequest()->input('per_page', 50));

        return JournalResource::collection($journals);
    }

    public function store(StoreJournalRequest $request): JsonResponse
    {
        $this->authorize('create', Journal::class);

        $journal = $this->journalMasterService->create($request->validated());

        return (new JournalResource($journal->load(['defaultAccount', 'suspenseAccount', 'outstandingReceiptsAccount', 'outstandingPaymentsAccount'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Journal $journal, JournalFilter $filter): JournalResource
    {
        $this->authorize('view', $journal);

        $filter->apply($journal->newQuery());

        $journal->loadMissing(['defaultAccount', 'suspenseAccount', 'outstandingReceiptsAccount', 'outstandingPaymentsAccount']);

        return new JournalResource($journal);
    }

    public function update(UpdateJournalRequest $request, Journal $journal): JournalResource|JsonResponse
    {
        $this->authorize('update', $journal);

        try {
            $updated = $this->journalMasterService->update($journal, $request->validated());

            return new JournalResource($updated);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Journal $journal): JsonResponse
    {
        $this->authorize('delete', $journal);

        try {
            $this->journalMasterService->delete($journal);

            return response()->json(['message' => 'Jurnal berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
