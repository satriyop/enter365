<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreQuotationActivityRequest;
use App\Http\Resources\Api\V1\QuotationActivityResource;
use App\Http\Resources\Api\V1\QuotationResource;
use App\Models\Accounting\Quotation;
use App\Models\Accounting\QuotationActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuotationFollowUpController extends Controller
{
    /**
     * List quotations that need follow-up.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Quotation::query()
            ->with(['contact', 'assignedTo'])
            ->active();

        // Filter by assigned user
        if ($request->has('assigned_to')) {
            $query->assignedTo($request->integer('assigned_to'));
        }

        // Filter by priority
        if ($request->boolean('high_priority_only')) {
            $query->highPriority();
        }

        // Filter by needs follow-up
        if ($request->boolean('needs_follow_up')) {
            $query->needsFollowUp();
        }

        // Filter by overdue follow-up
        if ($request->boolean('overdue_only')) {
            $query->overdueFollowUp();
        }

        // Sort options
        $sortField = $request->input('sort_by', 'next_follow_up_at');
        $sortDir = $request->input('sort_dir', 'asc');

        if ($sortField === 'next_follow_up_at') {
            // Put nulls at the end when sorting ascending
            $query->orderByRaw('next_follow_up_at IS NULL, next_follow_up_at '.$sortDir);
        } else {
            $query->orderBy($sortField, $sortDir);
        }

        $quotations = $query->paginate($request->input('per_page', 25));

        return QuotationResource::collection($quotations);
    }

    /**
     * Get activities for a quotation.
     */
    public function activities(Request $request, Quotation $quotation): AnonymousResourceCollection
    {
        $query = $quotation->activities()->with('user');

        if ($request->has('type')) {
            $query->ofType($request->input('type'));
        }

        $activities = $query->orderByDesc('activity_at')
            ->paginate($request->input('per_page', 25));

        return QuotationActivityResource::collection($activities);
    }

    /**
     * Record a new activity for a quotation.
     */
    public function storeActivity(StoreQuotationActivityRequest $request, Quotation $quotation): JsonResponse
    {
        $activity = DB::transaction(function () use ($request, $quotation) {
            // Create the activity
            $activity = $quotation->activities()->create([
                ...$request->validated(),
                'user_id' => $request->user()->id,
            ]);

            // Update quotation's last_contacted_at and follow_up_count
            $quotation->recordContact();

            // If activity includes next_follow_up_at, update quotation's next_follow_up_at
            if ($request->filled('next_follow_up_at')) {
                $quotation->next_follow_up_at = $request->input('next_follow_up_at');
                $quotation->save();
            }

            return $activity;
        });

        return (new QuotationActivityResource($activity->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Schedule follow-up for a quotation.
     */
    public function scheduleFollowUp(Request $request, Quotation $quotation): QuotationResource
    {
        $validated = $request->validate([
            'next_follow_up_at' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'next_follow_up_at.required' => 'Tanggal follow-up harus diisi.',
            'next_follow_up_at.after' => 'Tanggal follow-up harus di masa depan.',
        ]);

        DB::transaction(function () use ($validated, $quotation, $request) {
            $quotation->next_follow_up_at = $validated['next_follow_up_at'];
            $quotation->save();

            // Record activity if notes provided
            if (! empty($validated['notes'])) {
                $quotation->activities()->create([
                    'user_id' => $request->user()->id,
                    'type' => QuotationActivity::TYPE_FOLLOW_UP_SCHEDULED,
                    'description' => $validated['notes'],
                    'activity_at' => now(),
                    'next_follow_up_at' => $validated['next_follow_up_at'],
                ]);
            }
        });

        return new QuotationResource($quotation->fresh(['contact', 'assignedTo']));
    }

    /**
     * Assign quotation to a user.
     */
    public function assign(Request $request, Quotation $quotation): QuotationResource
    {
        $validated = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
        ], [
            'assigned_to.required' => 'User harus dipilih.',
            'assigned_to.exists' => 'User tidak ditemukan.',
        ]);

        $quotation->assigned_to = $validated['assigned_to'];
        $quotation->save();

        return new QuotationResource($quotation->fresh(['contact', 'assignedTo']));
    }

    /**
     * Update quotation priority.
     */
    public function updatePriority(Request $request, Quotation $quotation): QuotationResource
    {
        $validated = $request->validate([
            'priority' => [
                'required',
                Rule::in([
                    Quotation::PRIORITY_LOW,
                    Quotation::PRIORITY_NORMAL,
                    Quotation::PRIORITY_HIGH,
                    Quotation::PRIORITY_URGENT,
                ]),
            ],
        ], [
            'priority.required' => 'Prioritas harus diisi.',
            'priority.in' => 'Prioritas tidak valid.',
        ]);

        $quotation->priority = $validated['priority'];
        $quotation->save();

        return new QuotationResource($quotation->fresh(['contact', 'assignedTo']));
    }

    /**
     * Mark quotation as won.
     */
    public function markAsWon(Request $request, Quotation $quotation): QuotationResource|JsonResponse
    {
        // Only approved quotations can be marked as won
        if ($quotation->status !== Quotation::STATUS_APPROVED) {
            return response()->json([
                'message' => 'Hanya penawaran yang disetujui yang dapat ditandai sebagai menang.',
            ], 422);
        }

        // Already has outcome
        if ($quotation->outcome !== null) {
            return response()->json([
                'message' => 'Penawaran sudah memiliki hasil.',
            ], 422);
        }

        $validated = $request->validate([
            'won_reason' => ['nullable', 'string', Rule::in(array_keys(Quotation::WON_REASONS))],
            'outcome_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($quotation, $validated, $request) {
            $quotation->markAsWon($validated);

            // Record activity
            $quotation->activities()->create([
                'user_id' => $request->user()->id,
                'type' => QuotationActivity::TYPE_STATUS_CHANGE,
                'subject' => 'Penawaran Menang',
                'description' => $validated['outcome_notes'] ?? 'Penawaran ditandai sebagai menang.',
                'activity_at' => now(),
            ]);
        });

        return new QuotationResource($quotation->fresh(['contact', 'assignedTo']));
    }

    /**
     * Mark quotation as lost.
     */
    public function markAsLost(Request $request, Quotation $quotation): QuotationResource|JsonResponse
    {
        // Only approved/submitted quotations can be marked as lost
        if (! in_array($quotation->status, [Quotation::STATUS_APPROVED, Quotation::STATUS_SUBMITTED])) {
            return response()->json([
                'message' => 'Hanya penawaran yang diajukan atau disetujui yang dapat ditandai sebagai kalah.',
            ], 422);
        }

        // Already has outcome
        if ($quotation->outcome !== null) {
            return response()->json([
                'message' => 'Penawaran sudah memiliki hasil.',
            ], 422);
        }

        $validated = $request->validate([
            'lost_reason' => ['nullable', 'string', Rule::in(array_keys(Quotation::LOST_REASONS))],
            'lost_to_competitor' => ['nullable', 'string', 'max:100'],
            'outcome_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($quotation, $validated, $request) {
            $quotation->markAsLost($validated);

            // Record activity
            $quotation->activities()->create([
                'user_id' => $request->user()->id,
                'type' => QuotationActivity::TYPE_STATUS_CHANGE,
                'subject' => 'Penawaran Kalah',
                'description' => $validated['outcome_notes'] ?? 'Penawaran ditandai sebagai kalah.',
                'activity_at' => now(),
            ]);
        });

        return new QuotationResource($quotation->fresh(['contact', 'assignedTo']));
    }

    /**
     * Get win/loss statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $stats = DB::table('quotations')
            ->whereBetween('quotation_date', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->select([
                DB::raw('COUNT(*) as total_quotations'),
                DB::raw('COUNT(CASE WHEN outcome = \'won\' THEN 1 END) as won_count'),
                DB::raw('COUNT(CASE WHEN outcome = \'lost\' THEN 1 END) as lost_count'),
                DB::raw('COUNT(CASE WHEN outcome IS NULL AND status NOT IN (\'draft\', \'expired\', \'converted\') THEN 1 END) as pending_count'),
                DB::raw('SUM(CASE WHEN outcome = \'won\' THEN total ELSE 0 END) as won_value'),
                DB::raw('SUM(CASE WHEN outcome = \'lost\' THEN total ELSE 0 END) as lost_value'),
                DB::raw('SUM(CASE WHEN outcome IS NULL AND status NOT IN (\'draft\', \'expired\', \'converted\') THEN total ELSE 0 END) as pending_value'),
            ])
            ->first();

        $wonCount = $stats->won_count ?? 0;
        $lostCount = $stats->lost_count ?? 0;
        $totalDecided = $wonCount + $lostCount;

        return response()->json([
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'counts' => [
                'total' => $stats->total_quotations ?? 0,
                'won' => $wonCount,
                'lost' => $lostCount,
                'pending' => $stats->pending_count ?? 0,
            ],
            'values' => [
                'won' => (int) ($stats->won_value ?? 0),
                'lost' => (int) ($stats->lost_value ?? 0),
                'pending' => (int) ($stats->pending_value ?? 0),
            ],
            'conversion_rate' => $totalDecided > 0
                ? round(($wonCount / $totalDecided) * 100, 2)
                : 0,
            'lost_reasons' => $this->getLostReasonsBreakdown($startDate, $endDate),
            'won_reasons' => $this->getWonReasonsBreakdown($startDate, $endDate),
        ]);
    }

    /**
     * Get follow-up summary.
     */
    public function followUpSummary(Request $request): JsonResponse
    {
        $userId = $request->input('user_id');

        $query = Quotation::query()->active();

        if ($userId) {
            $query->assignedTo($userId);
        }

        $overdue = (clone $query)->overdueFollowUp()->count();
        $todayFollowUp = (clone $query)
            ->whereDate('next_follow_up_at', today())
            ->count();
        $upcomingWeek = (clone $query)
            ->whereDate('next_follow_up_at', '>', today())
            ->whereDate('next_follow_up_at', '<=', today()->addDays(7))
            ->count();
        $noFollowUp = (clone $query)
            ->whereNull('next_follow_up_at')
            ->whereNull('outcome')
            ->whereIn('status', [Quotation::STATUS_SUBMITTED, Quotation::STATUS_APPROVED])
            ->count();

        return response()->json([
            'overdue' => $overdue,
            'today' => $todayFollowUp,
            'upcoming_week' => $upcomingWeek,
            'no_follow_up_scheduled' => $noFollowUp,
        ]);
    }

    /**
     * Get lost reasons breakdown.
     *
     * @return array<array{reason: string, label: string, count: int, value: int}>
     */
    private function getLostReasonsBreakdown(string $startDate, string $endDate): array
    {
        $results = DB::table('quotations')
            ->whereBetween('quotation_date', [$startDate, $endDate])
            ->where('outcome', 'lost')
            ->whereNotNull('lost_reason')
            ->whereNull('deleted_at')
            ->groupBy('lost_reason')
            ->select([
                'lost_reason',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total) as value'),
            ])
            ->get();

        return $results->map(fn ($row) => [
            'reason' => $row->lost_reason,
            'label' => Quotation::LOST_REASONS[$row->lost_reason] ?? $row->lost_reason,
            'count' => (int) $row->count,
            'value' => (int) $row->value,
        ])->toArray();
    }

    /**
     * Get won reasons breakdown.
     *
     * @return array<array{reason: string, label: string, count: int, value: int}>
     */
    private function getWonReasonsBreakdown(string $startDate, string $endDate): array
    {
        $results = DB::table('quotations')
            ->whereBetween('quotation_date', [$startDate, $endDate])
            ->where('outcome', 'won')
            ->whereNotNull('won_reason')
            ->whereNull('deleted_at')
            ->groupBy('won_reason')
            ->select([
                'won_reason',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total) as value'),
            ])
            ->get();

        return $results->map(fn ($row) => [
            'reason' => $row->won_reason,
            'label' => Quotation::WON_REASONS[$row->won_reason] ?? $row->won_reason,
            'count' => (int) $row->count,
            'value' => (int) $row->value,
        ])->toArray();
    }
}
