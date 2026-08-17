<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\BudgetServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBudgetLineRequest;
use App\Http\Requests\Api\V1\StoreBudgetRequest;
use App\Http\Requests\Api\V1\UpdateBudgetLineRequest;
use App\Http\Requests\Api\V1\UpdateBudgetRequest;
use App\Http\Resources\Api\V1\BudgetLineResource;
use App\Http\Resources\Api\V1\BudgetResource;
use App\Models\Accounting\Budget;
use App\Models\Accounting\BudgetLine;
use App\Models\Accounting\FiscalPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BudgetController extends Controller
{
    public function __construct(
        protected BudgetServiceInterface $budgetService
    ) {}

    /**
     * List all budgets.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Budget::class);

        $query = Budget::query()->with(['fiscalPeriod'])->withCount('lines');

        // Filter by fiscal period
        if ($request->has('fiscal_period_id')) {
            $query->where('fiscal_period_id', $request->input('fiscal_period_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Search
        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
        }

        $budgets = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 25));

        return BudgetResource::collection($budgets);
    }

    /**
     * Create a new budget.
     */
    public function store(StoreBudgetRequest $request): JsonResponse
    {
        $this->authorize('create', Budget::class);

        $data = $request->validated();
        $lines = $data['lines'] ?? [];
        unset($data['lines']);

        $budget = $this->budgetService->createBudget($data, $lines);

        return (new BudgetResource($budget->load(['lines.account', 'fiscalPeriod'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a budget.
     */
    public function show(Budget $budget): BudgetResource
    {
        $this->authorize('view', $budget);

        return new BudgetResource(
            $budget->load(['lines.account', 'fiscalPeriod', 'approvedByUser'])
        );
    }

    /**
     * Update a budget.
     */
    public function update(UpdateBudgetRequest $request, Budget $budget): JsonResponse
    {
        $this->authorize('update', $budget);

        $budget = $this->budgetService->updateBudget($budget, $request->validated());

        return response()->json([
            'message' => 'Anggaran berhasil diperbarui.',
            'data' => new BudgetResource($budget),
        ]);
    }

    /**
     * Delete a budget.
     */
    public function destroy(Budget $budget): JsonResponse
    {
        $this->authorize('delete', $budget);

        $this->budgetService->deleteBudget($budget);

        return response()->json([
            'message' => 'Anggaran berhasil dihapus.',
        ]);
    }

    /**
     * Add a line to a budget.
     */
    public function addLine(StoreBudgetLineRequest $request, Budget $budget): JsonResponse
    {
        $this->authorize('update', $budget);

        $line = $this->budgetService->addBudgetLine($budget, $request->validated());

        return response()->json([
            'message' => 'Baris anggaran berhasil ditambahkan.',
            'data' => new BudgetLineResource($line->load('account')),
        ], 201);
    }

    /**
     * Update a budget line.
     */
    public function updateLine(UpdateBudgetLineRequest $request, Budget $budget, BudgetLine $line): JsonResponse
    {
        $this->authorize('update', $budget);

        $line = $this->budgetService->updateBudgetLine($budget, $line, $request->validated());

        return response()->json([
            'message' => 'Baris anggaran berhasil diperbarui.',
            'data' => new BudgetLineResource($line),
        ]);
    }

    /**
     * Delete a budget line.
     */
    public function deleteLine(Budget $budget, BudgetLine $line): JsonResponse
    {
        $this->authorize('update', $budget);

        $this->budgetService->deleteBudgetLine($budget, $line);

        return response()->json([
            'message' => 'Baris anggaran berhasil dihapus.',
        ]);
    }

    /**
     * Approve a budget.
     */
    public function approve(Budget $budget): JsonResponse
    {
        $this->authorize('approve', $budget);

        $budget = $this->budgetService->approve($budget);

        return response()->json([
            'message' => 'Anggaran berhasil disetujui.',
            'data' => new BudgetResource($budget),
        ]);
    }

    /**
     * Reopen a budget (set back to draft).
     */
    public function reopen(Budget $budget): JsonResponse
    {
        $this->authorize('update', $budget);

        $budget = $this->budgetService->reopen($budget);

        return response()->json([
            'message' => 'Anggaran berhasil dibuka kembali.',
            'data' => new BudgetResource($budget),
        ]);
    }

    /**
     * Close a budget.
     */
    public function close(Budget $budget): JsonResponse
    {
        $this->authorize('update', $budget);

        $budget = $this->budgetService->close($budget);

        return response()->json([
            'message' => 'Anggaran berhasil ditutup.',
            'data' => new BudgetResource($budget),
        ]);
    }

    /**
     * Copy a budget to a new fiscal period.
     */
    public function copy(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('update', $budget);

        $request->validate([
            'fiscal_period_id' => 'required|exists:fiscal_periods,id',
            'name' => 'nullable|string|max:100',
        ]);

        $newPeriod = FiscalPeriod::findOrFail($request->input('fiscal_period_id'));

        $newBudget = $this->budgetService->copyBudget(
            $budget,
            $newPeriod,
            $request->input('name')
        );

        return response()->json([
            'message' => 'Anggaran berhasil disalin.',
            'data' => new BudgetResource($newBudget),
        ], 201);
    }

    /**
     * Get budget vs actual comparison.
     */
    public function comparison(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('view', $budget);

        $month = $request->has('month') ? (int) $request->input('month') : null;

        $comparison = $this->budgetService->getBudgetVsActual($budget, $month);

        return response()->json([
            'budget' => [
                'id' => $budget->id,
                'name' => $budget->name,
                'fiscal_period' => $budget->fiscalPeriod->name,
            ],
            'month' => $month,
            'comparison' => $comparison,
        ]);
    }

    /**
     * Get monthly breakdown.
     */
    public function monthlyBreakdown(Budget $budget): JsonResponse
    {
        $this->authorize('view', $budget);

        $breakdown = $this->budgetService->getMonthlyBreakdown($budget);

        return response()->json([
            'budget' => [
                'id' => $budget->id,
                'name' => $budget->name,
                'fiscal_period' => $budget->fiscalPeriod->name,
            ],
            'monthly_breakdown' => $breakdown,
        ]);
    }

    /**
     * Get budget summary.
     */
    public function summary(Budget $budget): JsonResponse
    {
        $this->authorize('view', $budget);

        $summary = $this->budgetService->getBudgetSummary($budget);

        return response()->json($summary);
    }

    /**
     * Get over-budget accounts.
     */
    public function overBudget(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('view', $budget);

        $month = $request->has('month') ? (int) $request->input('month') : null;

        $overBudgetAccounts = $this->budgetService->getOverBudgetAccounts($budget, $month);

        return response()->json([
            'budget' => [
                'id' => $budget->id,
                'name' => $budget->name,
            ],
            'month' => $month,
            'over_budget_count' => $overBudgetAccounts->count(),
            'accounts' => $overBudgetAccounts->values(),
        ]);
    }
}
