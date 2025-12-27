<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\IndonesiaSolarDataResource;
use App\Http\Resources\Api\V1\PlnTariffResource;
use App\Models\Accounting\IndonesiaSolarData;
use App\Models\Accounting\PlnTariff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SolarDataController extends Controller
{
    /**
     * Lookup solar data by location.
     *
     * Supports lookup by:
     * - province + city (exact match)
     * - latitude + longitude (nearest location)
     *
     * @operationId lookupSolarData
     */
    public function lookup(Request $request): JsonResponse
    {
        // Lookup by coordinates
        if ($request->has('latitude') && $request->has('longitude')) {
            $request->validate([
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
            ]);

            $solarData = IndonesiaSolarData::findNearest(
                $request->input('latitude'),
                $request->input('longitude'),
                $request->input('max_distance_km', 100)
            );

            if (! $solarData) {
                return response()->json([
                    'message' => 'Tidak ada data solar untuk lokasi ini.',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'data' => new IndonesiaSolarDataResource($solarData),
            ]);
        }

        // Lookup by province and city
        if ($request->has('province') && $request->has('city')) {
            $request->validate([
                'province' => ['required', 'string', 'max:100'],
                'city' => ['required', 'string', 'max:100'],
            ]);

            $solarData = IndonesiaSolarData::findByLocation(
                $request->input('province'),
                $request->input('city')
            );

            if (! $solarData) {
                // Try to find nearest in the same province
                return response()->json([
                    'message' => 'Tidak ada data untuk kota ini. Gunakan koordinat untuk mencari lokasi terdekat.',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'data' => new IndonesiaSolarDataResource($solarData),
            ]);
        }

        return response()->json([
            'message' => 'Harap berikan province+city atau latitude+longitude.',
        ], 400);
    }

    /**
     * Get list of provinces with solar data.
     *
     * @operationId getSolarProvinces
     */
    public function provinces(): JsonResponse
    {
        $provinces = IndonesiaSolarData::getProvinces();

        return response()->json([
            'data' => $provinces,
        ]);
    }

    /**
     * Get list of cities in a province.
     *
     * @operationId getSolarCities
     */
    public function cities(Request $request): JsonResponse
    {
        $request->validate([
            'province' => ['required', 'string', 'max:100'],
        ]);

        $cities = IndonesiaSolarData::getCitiesInProvince($request->input('province'));

        return response()->json([
            'data' => $cities,
        ]);
    }

    /**
     * Get all solar data locations.
     *
     * @operationId listSolarLocations
     */
    public function locations(Request $request): AnonymousResourceCollection
    {
        $query = IndonesiaSolarData::query();

        if ($request->has('province')) {
            $query->inProvince($request->input('province'));
        }

        $locations = $query->orderBy('province')->orderBy('city')->get();

        return IndonesiaSolarDataResource::collection($locations);
    }

    /**
     * Get all active PLN tariffs.
     *
     * @operationId listPlnTariffs
     */
    public function tariffs(Request $request): AnonymousResourceCollection
    {
        $query = PlnTariff::query()->active();

        if ($request->has('customer_type')) {
            $query->ofType($request->input('customer_type'));
        }

        $tariffs = $query->orderBy('customer_type')
            ->orderBy('power_va_min')
            ->get();

        return PlnTariffResource::collection($tariffs);
    }

    /**
     * Get PLN tariffs grouped by customer type.
     *
     * @operationId getPlnTariffsGrouped
     */
    public function tariffsGrouped(): JsonResponse
    {
        $grouped = PlnTariff::getGroupedByType();

        $result = [];
        foreach ($grouped as $type => $tariffs) {
            $result[$type] = PlnTariffResource::collection($tariffs);
        }

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Get a specific PLN tariff by code.
     *
     * @operationId getPlnTariffByCode
     */
    public function tariffByCode(string $code): JsonResponse
    {
        $tariff = PlnTariff::findByCode($code);

        if (! $tariff) {
            return response()->json([
                'message' => 'Tarif tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'data' => new PlnTariffResource($tariff),
        ]);
    }
}
