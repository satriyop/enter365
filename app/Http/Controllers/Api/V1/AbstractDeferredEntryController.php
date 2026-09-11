<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\DeferredEntryServiceInterface;
use App\Http\Requests\Api\V1\StoreDeferredEntryRequest;
use App\Http\Requests\Api\V1\UpdateDeferredEntryRequest;
use App\Http\Resources\Api\V1\DeferredEntryResource;
use App\Models\Accounting\DeferredEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractDeferredEntryController extends Controller
{
    public function __construct(
        protected DeferredEntryServiceInterface $entries,
    ) {}

    abstract protected function kind(): string;

    abstract protected function deletedMessage(): string;

    /**
     * @queryParam search string Search by code or name. Example: SEWA
     * @queryParam status string Filter by draft, running, or closed. Example: running
     * @queryParam per_page int Items per page. Default: 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DeferredEntry::class);

        $query = DeferredEntry::query()
            ->ofKind($this->kind())
            ->with('contact')
            ->orderByDesc('id');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($inner) use ($search) {
                $inner->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        return DeferredEntryResource::collection(
            $query->paginate($request->integer('per_page', 50))
        );
    }

    public function store(StoreDeferredEntryRequest $request): JsonResponse
    {
        $this->authorize('create', DeferredEntry::class);

        $entry = $this->entries->create($this->kind(), $request->validated());

        return (new DeferredEntryResource($entry->load(['contact', 'lines'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(DeferredEntry $deferredEntry): DeferredEntryResource
    {
        $this->assertKind($deferredEntry);
        $this->authorize('view', $deferredEntry);

        return new DeferredEntryResource($deferredEntry->load(['contact', 'lines']));
    }

    public function update(UpdateDeferredEntryRequest $request, DeferredEntry $deferredEntry): DeferredEntryResource
    {
        $this->assertKind($deferredEntry);
        $this->authorize('update', $deferredEntry);

        return new DeferredEntryResource($this->entries->update($deferredEntry, $request->validated()));
    }

    public function destroy(DeferredEntry $deferredEntry): JsonResponse
    {
        $this->assertKind($deferredEntry);
        $this->authorize('delete', $deferredEntry);

        $this->entries->delete($deferredEntry);

        return $this->deleted($this->deletedMessage());
    }

    public function confirm(DeferredEntry $deferredEntry): DeferredEntryResource
    {
        $this->assertKind($deferredEntry);
        $this->authorize('update', $deferredEntry);

        return new DeferredEntryResource($this->entries->confirm($deferredEntry));
    }

    public function postRecognition(DeferredEntry $deferredEntry): DeferredEntryResource
    {
        $this->assertKind($deferredEntry);
        $this->authorize('update', $deferredEntry);

        return new DeferredEntryResource($this->entries->postNextRecognition($deferredEntry));
    }

    private function assertKind(DeferredEntry $entry): void
    {
        if ($entry->kind !== $this->kind()) {
            throw new NotFoundHttpException;
        }
    }
}
