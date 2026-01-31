<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sales\Quotations\Enums\QuotationOutcome;
use App\Domain\Sales\Quotations\Enums\QuotationPriority;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreQuotationActivityRequest;
use App\Http\Resources\Api\V1\QuotationActivityResource;
use App\Http\Resources\Api\V1\QuotationResource;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationActivity;
use App\Services\Sales\Quotation\QuotationStatisticsService;
use App\Services\Sales\QuotationFollowUpService;
use App\Services\Sales\QuotationOutcomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuotationFollowUpController extends Controller
{
    public function __construct(
        private QuotationOutcomeService $outcomeService,
        private QuotationFollowUpService $followUpService,
        private QuotationStatisticsService $statisticsService
    ) {}

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
            $this->followUpService->recordContact($quotation);

            // If activity includes next_follow_up_at, update quotation's next_follow_up_at
            if ($request->filled('next_follow_up_at')) {
                $this->followUpService->scheduleFollowUpAt($quotation, $request->input('next_follow_up_at'));
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
                Rule::in(QuotationPriority::ALL),
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
        // Check if quotation can be marked as won
        if (! $this->outcomeService->canMarkAsWon($quotation)) {
            if ($quotation->outcome !== null) {
                return response()->json([
                    'message' => 'Penawaran sudah memiliki hasil.',
                ], 422);
            }

            return response()->json([
                'message' => 'Hanya penawaran yang disetujui yang dapat ditandai sebagai menang.',
            ], 422);
        }

        $validated = $request->validate([
            'won_reason' => ['nullable', 'string', Rule::in(array_keys(QuotationOutcome::WON_REASONS))],
            'outcome_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $quotation = DB::transaction(function () use ($quotation, $validated, $request) {
            $quotation = $this->outcomeService->markAsWon($quotation, $validated);

            // Record activity
            $quotation->activities()->create([
                'user_id' => $request->user()->id,
                'type' => QuotationActivity::TYPE_STATUS_CHANGE,
                'subject' => 'Penawaran Menang',
                'description' => $validated['outcome_notes'] ?? 'Penawaran ditandai sebagai menang.',
                'activity_at' => now(),
            ]);

            return $quotation;
        });

        return new QuotationResource($quotation->fresh(['contact', 'assignedTo']));
    }

    /**
     * Mark quotation as lost.
     */
    public function markAsLost(Request $request, Quotation $quotation): QuotationResource|JsonResponse
    {
        // Check if quotation can be marked as lost
        if (! $this->outcomeService->canMarkAsLost($quotation)) {
            if ($quotation->outcome !== null) {
                return response()->json([
                    'message' => 'Penawaran sudah memiliki hasil.',
                ], 422);
            }

            return response()->json([
                'message' => 'Hanya penawaran yang diajukan atau disetujui yang dapat ditandai sebagai kalah.',
            ], 422);
        }

        $validated = $request->validate([
            'lost_reason' => ['nullable', 'string', Rule::in(array_keys(QuotationOutcome::LOST_REASONS))],
            'lost_to_competitor' => ['nullable', 'string', 'max:100'],
            'outcome_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $quotation = DB::transaction(function () use ($quotation, $validated, $request) {
            $quotation = $this->outcomeService->markAsLost($quotation, $validated);

            // Record activity
            $quotation->activities()->create([
                'user_id' => $request->user()->id,
                'type' => QuotationActivity::TYPE_STATUS_CHANGE,
                'subject' => 'Penawaran Kalah',
                'description' => $validated['outcome_notes'] ?? 'Penawaran ditandai sebagai kalah.',
                'activity_at' => now(),
            ]);

            return $quotation;
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

        $stats = $this->statisticsService->getOutcomeStatistics($startDate, $endDate);

        $wonCount = $stats->won_count;
        $lostCount = $stats->lost_count;
        $totalDecided = $wonCount + $lostCount;

        return response()->json([
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'counts' => [
                'total' => $stats->total_quotations,
                'won' => $wonCount,
                'lost' => $lostCount,
                'pending' => $stats->pending_count,
            ],
            'values' => [
                'won' => $stats->won_value,
                'lost' => $stats->lost_value,
                'pending' => $stats->pending_value,
            ],
            'conversion_rate' => $totalDecided > 0
                ? round(($wonCount / $totalDecided) * 100, 2)
                : 0,
            'lost_reasons' => $this->statisticsService->getLostReasonsBreakdown($startDate, $endDate),
            'won_reasons' => $this->statisticsService->getWonReasonsBreakdown($startDate, $endDate),
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
            ->whereIn('status', [DocumentStatus::Submitted, DocumentStatus::Approved])
            ->count();

        return response()->json([
            'overdue' => $overdue,
            'today' => $todayFollowUp,
            'upcoming_week' => $upcomingWeek,
            'no_follow_up_scheduled' => $noFollowUp,
        ]);
    }
}
