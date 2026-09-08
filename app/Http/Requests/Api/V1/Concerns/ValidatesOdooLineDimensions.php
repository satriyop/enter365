<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Casts\AnalyticDistributionCast;
use App\Models\Accounting\AnalyticAccount;

trait ValidatesOdooLineDimensions
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function lineDimensionRules(bool $accountRequired): array
    {
        return [
            'items.*.expense_account_id' => [
                $accountRequired ? 'required' : 'nullable',
                'integer',
                'exists:accounts,id',
            ],
            'items.*.account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'items.*.analytic_distribution' => ['nullable', 'array'],
            'items.*.analytic_distribution.*' => ['numeric', 'min:0', 'max:100'],
            'items.*.tax_tag_ids' => ['nullable', 'array'],
            'items.*.tax_tag_ids.*' => ['integer', 'min:1', 'exists:tax_tags,id'],
        ];
    }

    protected function mergeAccountIdAlias(): void
    {
        $items = $this->input('items');
        if (! is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            if (! array_key_exists('expense_account_id', $item) && array_key_exists('account_id', $item)) {
                $items[$index]['expense_account_id'] = $item['account_id'];
            }
        }

        $this->merge(['items' => $items]);
    }

    protected function validateAnalyticDistributionKeys(\Illuminate\Validation\Validator $validator): void
    {
        $items = $this->input('items', []);
        if (! is_array($items)) {
            return;
        }

        $analyticIds = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! is_array($item['analytic_distribution'] ?? null)) {
                continue;
            }
            foreach (array_keys($item['analytic_distribution']) as $id) {
                if (is_numeric($id)) {
                    $analyticIds[] = (int) $id;
                }
            }
        }

        $existingIds = $analyticIds === []
            ? []
            : AnalyticAccount::query()
                ->whereIn('id', array_values(array_unique($analyticIds)))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        $existingSet = array_flip($existingIds);

        foreach ($items as $index => $item) {
            if (! is_array($item) || ! is_array($item['analytic_distribution'] ?? null)) {
                continue;
            }
            foreach (array_keys($item['analytic_distribution']) as $id) {
                if (! is_numeric($id) || ! isset($existingSet[(int) $id])) {
                    $validator->errors()->add(
                        "items.{$index}.analytic_distribution",
                        'Akun analitik tidak ditemukan.'
                    );
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function restoreAnalyticDistributionKeys(array $validated): array
    {
        if (! is_array($validated['items'] ?? null)) {
            return $validated;
        }

        $rawItems = $this->input('items', []);

        foreach ($validated['items'] as $index => $item) {
            if (! is_array($item) || ! array_key_exists('analytic_distribution', $item)) {
                continue;
            }

            $raw = is_array($rawItems) && is_array($rawItems[$index] ?? null)
                ? ($rawItems[$index]['analytic_distribution'] ?? null)
                : $item['analytic_distribution'];

            $validated['items'][$index]['analytic_distribution'] = AnalyticDistributionCast::asObject($raw);
        }

        return $validated;
    }
}
