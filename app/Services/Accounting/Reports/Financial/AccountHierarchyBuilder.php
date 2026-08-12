<?php

namespace App\Services\Accounting\Reports\Financial;

use Illuminate\Support\Collection;

class AccountHierarchyBuilder
{
    /**
     * Build a hierarchical tree from flat balance items using Account parent_id relationships.
     *
     * Returns only top-level nodes (root accounts or accounts whose parents have no balance).
     * Each node contains:
     * - balance: own transactions only
     * - rollup_balance: own + all descendants
     * - children: recursive collection
     * - depth: nesting level
     */
    public function build(Collection $flatBalances, Collection $accounts): Collection
    {
        // Build a map of account_id => parent_id from the full accounts collection
        $parentMap = $accounts->pluck('parent_id', 'id');

        // Index flat balances by account_id
        $balanceById = $flatBalances->keyBy('account_id');

        // Build a subtype map from all accounts
        $subtypeMap = $accounts->pluck('subtype', 'id');

        // Collect ancestor IDs within the same subtype boundary
        $ancestorsToInclude = collect();

        foreach ($flatBalances as $item) {
            if ($item->balance == 0) {
                continue; // Only trace ancestors for items that have actual balance
            }
            $currentParentId = $parentMap->get($item->account_id);
            $itemSubtype = $item->subtype;
            while ($currentParentId !== null) {
                // Stop if parent has different subtype
                if ($subtypeMap->get($currentParentId) !== $itemSubtype) {
                    break;
                }
                if ($ancestorsToInclude->contains($currentParentId)) {
                    break;
                }
                $ancestorsToInclude->push($currentParentId);
                $currentParentId = $parentMap->get($currentParentId);
            }
        }

        // Add zero-balance parent nodes so tree is complete
        foreach ($ancestorsToInclude as $ancestorId) {
            if (! $balanceById->has($ancestorId)) {
                $account = $accounts->firstWhere('id', $ancestorId);
                if ($account) {
                    $node = (object) [
                        'account_id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                        'type' => $account->type,
                        'subtype' => $account->subtype,
                        'parent_id' => $account->parent_id,
                        'balance' => 0,
                    ];
                    $balanceById->put($ancestorId, $node);
                }
            }
        }

        // Build child-lookup: parent_id => [child items]
        // Only nest within same subtype to keep category grouping intact
        $childrenOf = [];
        foreach ($balanceById as $item) {
            $pid = $item->parent_id;
            if ($pid !== null && $balanceById->has($pid)) {
                $parent = $balanceById->get($pid);
                if ($parent->subtype === $item->subtype) {
                    $childrenOf[$pid][] = $item;
                }
            }
        }

        // Recursive tree builder
        $buildTree = function ($item, int $depth) use (&$buildTree, &$childrenOf): object {
            $children = collect($childrenOf[$item->account_id] ?? [])
                ->map(fn ($child) => $buildTree($child, $depth + 1))
                ->sortBy('code')
                ->values();

            $rollupBalance = $item->balance + $children->sum('rollup_balance');

            return (object) [
                'account_id' => $item->account_id,
                'code' => $item->code,
                'name' => $item->name,
                'type' => $item->type,
                'subtype' => $item->subtype,
                'balance' => $item->balance,
                'rollup_balance' => $rollupBalance,
                'children' => $children,
                'depth' => $depth,
            ];
        };

        // Find root nodes: items whose parent is null, whose parent isn't in the balance set,
        // or whose parent has a different subtype (don't cross subtype boundaries)
        $roots = $balanceById->filter(function ($item) use ($balanceById) {
            if ($item->parent_id === null || ! $balanceById->has($item->parent_id)) {
                return true;
            }

            $parent = $balanceById->get($item->parent_id);

            return $parent->subtype !== $item->subtype;
        });

        return $roots->map(fn ($item) => $buildTree($item, 0))
            ->filter(fn ($item) => $item->rollup_balance != 0)
            ->sortBy('code')
            ->values();
    }
}
