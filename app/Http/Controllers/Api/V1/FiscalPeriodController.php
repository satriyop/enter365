<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFiscalPeriodRequest;
use App\Http\Resources\Api\V1\FiscalPeriodResource;
use App\Models\Accounting\FiscalPeriod;
use App\Services\Accounting\FiscalPeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FiscalPeriodController extends Controller
{
    public function __construct(
        private FiscalPeriodService $fiscalPeriodService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FiscalPeriod::query();

        if ($request->has('is_closed')) {
            $query->where('is_closed', $request->boolean('is_closed'));
        }

        if ($request->has('is_locked')) {
            $query->where('is_locked', $request->boolean('is_locked'));
        }

        if ($request->has('year')) {
            $year = $request->input('year');
            $query->whereYear('start_date', $year);
        }

        $periods = $query->orderByDesc('start_date')->paginate($request->input('per_page', 25));

        return FiscalPeriodResource::collection($periods);
    }

    public function store(StoreFiscalPeriodRequest $request): JsonResponse
    {
        // Check for overlapping periods
        $overlap = FiscalPeriod::query()
            ->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->start_date, $request->end_date])
                    ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('start_date', '<=', $request->start_date)
                            ->where('end_date', '>=', $request->end_date);
                    });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Periode fiskal bertumpang tindih dengan periode yang sudah ada.',
            ], 422);
        }

        $period = FiscalPeriod::create([
            ...$request->validated(),
            'is_closed' => false,
            'is_locked' => false,
        ]);

        return (new FiscalPeriodResource($period))
            ->response()
            ->setStatusCode(201);
    }

    public function show(FiscalPeriod $fiscalPeriod): FiscalPeriodResource
    {
        return new FiscalPeriodResource($fiscalPeriod->load('closingEntry'));
    }

    public function lock(FiscalPeriod $fiscalPeriod): JsonResponse
    {
        if ($fiscalPeriod->is_closed) {
            return response()->json([
                'message' => 'Periode yang sudah ditutup tidak bisa dikunci.',
            ], 422);
        }

        if ($fiscalPeriod->is_locked) {
            return response()->json([
                'message' => 'Periode sudah dikunci.',
            ], 422);
        }

        $fiscalPeriod->lock();

        return response()->json([
            'message' => 'Periode fiskal berhasil dikunci.',
            'data' => new FiscalPeriodResource($fiscalPeriod->fresh()),
        ]);
    }

    public function unlock(FiscalPeriod $fiscalPeriod): JsonResponse
    {
        if ($fiscalPeriod->is_closed) {
            return response()->json([
                'message' => 'Periode yang sudah ditutup tidak bisa dibuka kuncinya.',
            ], 422);
        }

        if (! $fiscalPeriod->is_locked) {
            return response()->json([
                'message' => 'Periode tidak dalam keadaan terkunci.',
            ], 422);
        }

        $fiscalPeriod->unlock();

        return response()->json([
            'message' => 'Periode fiskal berhasil dibuka kuncinya.',
            'data' => new FiscalPeriodResource($fiscalPeriod->fresh()),
        ]);
    }

    public function close(Request $request, FiscalPeriod $fiscalPeriod): JsonResponse
    {
        $result = $this->fiscalPeriodService->closePeriod(
            $fiscalPeriod,
            $request->input('notes')
        );

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => new FiscalPeriodResource($fiscalPeriod->fresh('closingEntry')),
            'closing_entry_id' => $result['closing_entry']?->id,
        ]);
    }

    public function reopen(FiscalPeriod $fiscalPeriod): JsonResponse
    {
        if (! $fiscalPeriod->is_closed) {
            return response()->json([
                'message' => 'Periode belum ditutup.',
            ], 422);
        }

        $success = $this->fiscalPeriodService->reopenPeriod($fiscalPeriod);

        if (! $success) {
            return response()->json([
                'message' => 'Gagal membuka kembali periode fiskal.',
            ], 500);
        }

        return response()->json([
            'message' => 'Periode fiskal berhasil dibuka kembali.',
            'data' => new FiscalPeriodResource($fiscalPeriod->fresh()),
        ]);
    }

    public function closingChecklist(FiscalPeriod $fiscalPeriod): JsonResponse
    {
        $checklist = $this->fiscalPeriodService->getClosingChecklist($fiscalPeriod);

        $canClose = collect($checklist)->every(fn ($item) => $item['status'] !== 'error');

        return response()->json([
            'period' => new FiscalPeriodResource($fiscalPeriod),
            'can_close' => $canClose,
            'checklist' => $checklist,
        ]);
    }
}
