<?php

namespace App\Contracts\Accounting;

use App\Models\Accounting\AnalyticBudget;
use App\Models\Accounting\AnalyticDistributionModel;
use App\Models\Accounting\AnalyticPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AnalyticDimensionServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createPlan(array $data): AnalyticPlan;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePlan(AnalyticPlan $plan, array $data): AnalyticPlan;

    public function deletePlan(AnalyticPlan $plan): void;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDistributionModel(array $data): AnalyticDistributionModel;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDistributionModel(AnalyticDistributionModel $model, array $data): AnalyticDistributionModel;

    public function deleteDistributionModel(AnalyticDistributionModel $model): void;

    /**
     * @return array<string, float|int>|null
     */
    public function matchDistribution(?int $partnerId, ?int $accountId, ?int $productId): ?array;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function items(array $filters): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createBudget(array $data): AnalyticBudget;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateBudget(AnalyticBudget $budget, array $data): AnalyticBudget;

    public function deleteBudget(AnalyticBudget $budget): void;

    public function withActuals(AnalyticBudget $budget): AnalyticBudget;
}
