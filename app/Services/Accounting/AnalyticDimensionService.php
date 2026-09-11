<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Casts\AnalyticDistributionCast;
use App\Contracts\Accounting\AnalyticDimensionServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\AnalyticAccount;
use App\Models\Accounting\AnalyticBudget;
use App\Models\Accounting\AnalyticDistributionModel;
use App\Models\Accounting\AnalyticPlan;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Base\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class AnalyticDimensionService extends BaseService implements AnalyticDimensionServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPlan(array $data): AnalyticPlan
    {
        return $this->executeInTransaction('create_analytic_plan', function () use ($data) {
            return AnalyticPlan::query()->create(Arr::only($data, [
                'code',
                'name',
                'parent_id',
                'default_applicability',
                'is_active',
                'notes',
            ]));
        }, ['code' => $data['code'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePlan(AnalyticPlan $plan, array $data): AnalyticPlan
    {
        return $this->executeInTransaction('update_analytic_plan', function () use ($plan, $data) {
            if (isset($data['parent_id']) && (int) $data['parent_id'] === $plan->id) {
                throw new BusinessRuleException('Rencana analitik tidak boleh menjadi induk dirinya sendiri.');
            }

            $plan->update(Arr::only($data, [
                'code',
                'name',
                'parent_id',
                'default_applicability',
                'is_active',
                'notes',
            ]));

            return $plan->fresh(['parent', 'analyticAccounts']) ?? $plan;
        }, ['analytic_plan_id' => $plan->id]);
    }

    public function deletePlan(AnalyticPlan $plan): void
    {
        $this->executeInTransaction('delete_analytic_plan', function () use ($plan) {
            if ($plan->analyticAccounts()->exists()) {
                throw new BusinessRuleException(
                    'Rencana analitik masih dipakai akun analitik.',
                    ['analytic_plan_id' => $plan->id]
                );
            }

            $plan->children()->update(['parent_id' => $plan->parent_id]);
            $plan->delete();
        }, ['analytic_plan_id' => $plan->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDistributionModel(array $data): AnalyticDistributionModel
    {
        return $this->executeInTransaction('create_analytic_distribution_model', function () use ($data) {
            $data['analytic_distribution'] = AnalyticDistributionCast::asObject($data['analytic_distribution'] ?? null);

            return AnalyticDistributionModel::query()->create(Arr::only($data, [
                'name',
                'partner_id',
                'account_prefix',
                'product_id',
                'analytic_distribution',
                'sequence',
                'is_active',
            ]));
        }, ['name' => $data['name'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDistributionModel(AnalyticDistributionModel $model, array $data): AnalyticDistributionModel
    {
        return $this->executeInTransaction('update_analytic_distribution_model', function () use ($model, $data) {
            if (array_key_exists('analytic_distribution', $data)) {
                $data['analytic_distribution'] = AnalyticDistributionCast::asObject($data['analytic_distribution']);
            }

            $model->update(Arr::only($data, [
                'name',
                'partner_id',
                'account_prefix',
                'product_id',
                'analytic_distribution',
                'sequence',
                'is_active',
            ]));

            return $model->fresh(['partner', 'product']) ?? $model;
        }, ['analytic_distribution_model_id' => $model->id]);
    }

    public function deleteDistributionModel(AnalyticDistributionModel $model): void
    {
        $this->executeInTransaction('delete_analytic_distribution_model', function () use ($model) {
            $model->delete();
        }, ['analytic_distribution_model_id' => $model->id]);
    }

    /**
     * @return array<string, float|int>|null
     */
    public function matchDistribution(?int $partnerId, ?int $accountId, ?int $productId): ?array
    {
        $accountCode = $accountId
            ? Account::query()->whereKey($accountId)->value('code')
            : null;

        $models = AnalyticDistributionModel::query()
            ->where('is_active', true)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();

        foreach ($models as $model) {
            if ($model->partner_id && (int) $model->partner_id !== $partnerId) {
                continue;
            }
            if ($model->product_id && (int) $model->product_id !== $productId) {
                continue;
            }
            if (filled($model->account_prefix)) {
                if (! is_string($accountCode) || ! str_starts_with($accountCode, (string) $model->account_prefix)) {
                    continue;
                }
            }

            $distribution = $model->analytic_distribution;

            return is_array($distribution) ? $distribution : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function items(array $filters): LengthAwarePaginator
    {
        $rows = $this->explodeItems($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($filters['per_page'] ?? 50)));

        return new Paginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createBudget(array $data): AnalyticBudget
    {
        return $this->executeInTransaction('create_analytic_budget', function () use ($data) {
            $this->assertBudgetDates($data);
            $lines = $data['lines'] ?? [];
            unset($data['lines']);
            $data['created_by'] = $this->getUserId();
            $data['status'] = AnalyticBudget::STATUS_DRAFT;

            $budget = AnalyticBudget::query()->create(Arr::only($data, [
                'name',
                'date_from',
                'date_to',
                'status',
                'notes',
                'created_by',
            ]));

            $this->syncBudgetLines($budget, is_array($lines) ? $lines : []);

            return $budget->fresh(['lines.analyticAccount']) ?? $budget;
        }, ['name' => $data['name'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateBudget(AnalyticBudget $budget, array $data): AnalyticBudget
    {
        return $this->executeInTransaction('update_analytic_budget', function () use ($budget, $data) {
            if (! $budget->isDraft() && array_key_exists('lines', $data)) {
                throw new BusinessRuleException(
                    'Anggaran analitik yang sudah dibuka tidak bisa diubah barisnya.',
                    ['analytic_budget_id' => $budget->id]
                );
            }

            $merged = array_merge($budget->only(['date_from', 'date_to']), $data);
            $this->assertBudgetDates($merged);

            $lines = $data['lines'] ?? null;
            unset($data['lines']);

            $budget->update(Arr::only($data, [
                'name',
                'date_from',
                'date_to',
                'status',
                'notes',
            ]));

            if (is_array($lines)) {
                $budget->lines()->delete();
                $this->syncBudgetLines($budget, $lines);
            }

            return $budget->fresh(['lines.analyticAccount']) ?? $budget;
        }, ['analytic_budget_id' => $budget->id]);
    }

    public function deleteBudget(AnalyticBudget $budget): void
    {
        $this->executeInTransaction('delete_analytic_budget', function () use ($budget) {
            if (! $budget->isDraft()) {
                throw new BusinessRuleException(
                    'Hanya anggaran analitik draf yang bisa dihapus.',
                    ['analytic_budget_id' => $budget->id]
                );
            }

            $budget->lines()->delete();
            $budget->delete();
        }, ['analytic_budget_id' => $budget->id]);
    }

    public function withActuals(AnalyticBudget $budget): AnalyticBudget
    {
        $actuals = $this->actualsByAccount(
            $budget->date_from?->toDateString() ?? '',
            $budget->date_to?->toDateString() ?? '',
        );

        $budget->loadMissing('lines.analyticAccount');
        foreach ($budget->lines as $line) {
            $actual = (int) ($actuals[$line->analytic_account_id] ?? 0);
            $line->setAttribute('actual_amount', $actual);
            $line->setAttribute('variance', (int) $line->planned_amount - $actual);
        }

        return $budget;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, mixed>
     */
    private function explodeItems(array $filters): Collection
    {
        $query = JournalEntryLine::query()
            ->whereNotNull('analytic_distribution')
            ->whereHas('journalEntry', function ($inner) use ($filters) {
                $inner->where('is_posted', true);
                if (filled($filters['from'] ?? null)) {
                    $inner->whereDate('entry_date', '>=', $filters['from']);
                }
                if (filled($filters['to'] ?? null)) {
                    $inner->whereDate('entry_date', '<=', $filters['to']);
                }
            })
            ->with(['journalEntry', 'account', 'partner'])
            ->orderByDesc('id');

        $lines = $query->get();
        $wanted = isset($filters['analytic_account_id']) ? (int) $filters['analytic_account_id'] : null;

        $rows = collect();
        foreach ($lines as $line) {
            $distribution = $line->analytic_distribution;
            if (! is_array($distribution) || $distribution === []) {
                continue;
            }

            $signed = (int) $line->debit - (int) $line->credit;
            foreach ($distribution as $analyticId => $percentage) {
                $analyticId = (int) $analyticId;
                if ($wanted && $analyticId !== $wanted) {
                    continue;
                }

                $rows->push([
                    'id' => $line->id.'-'.$analyticId,
                    'journal_entry_line_id' => $line->id,
                    'journal_entry_id' => $line->journal_entry_id,
                    'entry_number' => $line->journalEntry?->entry_number,
                    'entry_date' => $line->journalEntry?->entry_date?->toDateString(),
                    'analytic_account_id' => $analyticId,
                    'account_id' => $line->account_id,
                    'partner_id' => $line->partner_id,
                    'percentage' => (float) $percentage,
                    'amount' => (int) round($signed * ((float) $percentage) / 100),
                    'description' => $line->description,
                    'account' => $line->account ? [
                        'id' => $line->account->id,
                        'code' => $line->account->code,
                        'name' => $line->account->name,
                    ] : null,
                    'partner' => $line->partner ? [
                        'id' => $line->partner->id,
                        'name' => $line->partner->name,
                    ] : null,
                ]);
            }
        }

        $names = AnalyticAccount::query()
            ->whereIn('id', $rows->pluck('analytic_account_id')->unique()->filter()->all())
            ->get()
            ->keyBy('id');

        return $rows->map(function (array $row) use ($names): array {
            $analytic = $names->get($row['analytic_account_id']);
            $row['analytic_account'] = $analytic ? [
                'id' => $analytic->id,
                'code' => $analytic->code,
                'name' => $analytic->name,
            ] : null;

            return $row;
        })->values();
    }

    /**
     * @return array<int, int>
     */
    private function actualsByAccount(string $from, string $to): array
    {
        $rows = $this->explodeItems(['from' => $from, 'to' => $to]);
        $sums = [];
        foreach ($rows as $row) {
            $id = (int) $row['analytic_account_id'];
            $sums[$id] = ($sums[$id] ?? 0) + (int) $row['amount'];
        }

        return $sums;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertBudgetDates(array $data): void
    {
        $from = (string) ($data['date_from'] ?? '');
        $to = (string) ($data['date_to'] ?? '');
        if ($from !== '' && $to !== '' && $from > $to) {
            throw new BusinessRuleException('Tanggal mulai anggaran harus sebelum tanggal akhir.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncBudgetLines(AnalyticBudget $budget, array $lines): void
    {
        foreach ($lines as $line) {
            $budget->lines()->create([
                'analytic_account_id' => $line['analytic_account_id'],
                'planned_amount' => (int) $line['planned_amount'],
            ]);
        }
    }
}
