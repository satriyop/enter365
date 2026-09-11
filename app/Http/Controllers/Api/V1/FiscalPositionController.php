<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\FiscalPositionServiceInterface;
use App\Http\Requests\Api\V1\StoreFiscalPositionRequest;
use App\Http\Requests\Api\V1\UpdateFiscalPositionRequest;
use App\Http\Resources\Api\V1\FiscalPositionResource;
use App\Models\Accounting\FiscalPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FiscalPositionController extends Controller
{
    public function __construct(
        private FiscalPositionServiceInterface $fiscalPositions,
    ) {}

    /**
     * List fiscal positions (tax/account mapping master). Distinct from fiscal periods.
     *
     * @queryParam search string Search by code or name. Example: EXPORT
     * @queryParam is_active bool Filter by active status. Example: 1
     * @queryParam per_page int Items per page. Default: 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FiscalPosition::class);

        $query = FiscalPosition::query()
            ->with(['taxMaps.sourceTax', 'taxMaps.destTax', 'accountMaps.sourceAccount', 'accountMaps.destAccount'])
            ->orderBy('code');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($inner) use ($search) {
                $inner->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return FiscalPositionResource::collection(
            $query->paginate($request->integer('per_page', 50))
        );
    }

    /**
     * Create a fiscal position with tax and account maps.
     */
    public function store(StoreFiscalPositionRequest $request): JsonResponse
    {
        $this->authorize('create', FiscalPosition::class);

        $position = $this->fiscalPositions->create($request->validated());

        return (new FiscalPositionResource($position))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a fiscal position with maps.
     */
    public function show(FiscalPosition $fiscalPosition): FiscalPositionResource
    {
        $this->authorize('view', $fiscalPosition);

        $fiscalPosition->load([
            'taxMaps.sourceTax',
            'taxMaps.destTax',
            'accountMaps.sourceAccount',
            'accountMaps.destAccount',
        ]);

        return new FiscalPositionResource($fiscalPosition);
    }

    /**
     * Update a fiscal position and replace maps when provided.
     */
    public function update(UpdateFiscalPositionRequest $request, FiscalPosition $fiscalPosition): FiscalPositionResource
    {
        $this->authorize('update', $fiscalPosition);

        $position = $this->fiscalPositions->update($fiscalPosition, $request->validated());

        return new FiscalPositionResource($position);
    }

    /**
     * Delete a fiscal position that is not assigned to contacts.
     */
    public function destroy(FiscalPosition $fiscalPosition): JsonResponse
    {
        $this->authorize('delete', $fiscalPosition);

        $this->fiscalPositions->delete($fiscalPosition);

        return $this->deleted('Posisi fiskal berhasil dihapus.');
    }
}
