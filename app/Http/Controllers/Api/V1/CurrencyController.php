<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\AccountingConfigServiceInterface;
use App\Http\Requests\Api\V1\StoreCurrencyRequest;
use App\Http\Requests\Api\V1\UpdateCurrencyRequest;
use App\Http\Resources\Api\V1\CurrencyResource;
use App\Models\Accounting\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurrencyController extends Controller
{
    public function __construct(
        private AccountingConfigServiceInterface $config,
    ) {}

    /**
     * List currencies (Odoo Configuration › Currencies).
     *
     * @queryParam search string Search by code or name. Example: USD
     * @queryParam is_active bool Filter by active. Example: 1
     * @queryParam per_page int Default 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Currency::class);

        $query = Currency::query()->orderByDesc('is_base_currency')->orderBy('code');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($inner) use ($search) {
                $inner->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return CurrencyResource::collection($query->paginate($request->integer('per_page', 50)));
    }

    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        $this->authorize('create', Currency::class);

        $currency = $this->config->createCurrency($request->validated());

        return (new CurrencyResource($currency))->response()->setStatusCode(201);
    }

    public function show(Currency $currency): CurrencyResource
    {
        $this->authorize('view', $currency);

        return new CurrencyResource($currency->load('exchangeRates'));
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): CurrencyResource
    {
        $this->authorize('update', $currency);

        return new CurrencyResource($this->config->updateCurrency($currency, $request->validated()));
    }

    public function destroy(Currency $currency): JsonResponse
    {
        $this->authorize('delete', $currency);

        $this->config->deleteCurrency($currency);

        return $this->deleted('Mata uang berhasil dihapus.');
    }
}
