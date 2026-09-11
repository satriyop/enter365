<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\AccountingConfigServiceInterface;
use App\Http\Requests\Api\V1\StoreCashRoundingRequest;
use App\Http\Requests\Api\V1\UpdateCashRoundingRequest;
use App\Http\Resources\Api\V1\CashRoundingResource;
use App\Models\Accounting\CashRounding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashRoundingController extends Controller
{
    public function __construct(
        private AccountingConfigServiceInterface $config,
    ) {}

    /**
     * List cash rounding rules (Odoo Configuration › Cash Roundings).
     *
     * @queryParam search string Search by name. Example: Rp100
     * @queryParam is_active bool Filter by active. Example: 1
     * @queryParam per_page int Default 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CashRounding::class);

        $query = CashRounding::query()->with(['profitAccount', 'lossAccount'])->orderBy('name');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', '%'.$search.'%');
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return CashRoundingResource::collection($query->paginate($request->integer('per_page', 50)));
    }

    public function store(StoreCashRoundingRequest $request): JsonResponse
    {
        $this->authorize('create', CashRounding::class);

        $rounding = $this->config->createCashRounding($request->validated());

        return (new CashRoundingResource($rounding->load(['profitAccount', 'lossAccount'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CashRounding $cashRounding): CashRoundingResource
    {
        $this->authorize('view', $cashRounding);

        return new CashRoundingResource($cashRounding->load(['profitAccount', 'lossAccount']));
    }

    public function update(UpdateCashRoundingRequest $request, CashRounding $cashRounding): CashRoundingResource
    {
        $this->authorize('update', $cashRounding);

        return new CashRoundingResource($this->config->updateCashRounding($cashRounding, $request->validated()));
    }

    public function destroy(CashRounding $cashRounding): JsonResponse
    {
        $this->authorize('delete', $cashRounding);

        $this->config->deleteCashRounding($cashRounding);

        return $this->deleted('Aturan pembulatan kas berhasil dihapus.');
    }
}
