<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Enums\Pos\PosSaleStatus;
use App\Enums\Pos\PosSessionStatus;
use Illuminate\Support\Facades\DB;

class PosShopHomeService
{
    public const LOW_STOCK_BELOW = 10;

    /**
     * Read-only Kopitiam shop home. Does not open or close tills.
     *
     * @return array{
     *     open_sessions: list<array{id: int, session_number: string, cashier_name: string, warehouse_name: string, hold_count: int}>,
     *     open_hold_count: int,
     *     today: array{sale_count: int, omzet_amount: int, last_sale_number: string|null, last_sold_at: string|null},
     *     low_stock: list<array{product_id: int, sku: string, name: string, quantity: int}>,
     *     draft_journal_count: int
     * }
     */
    public function summary(): array
    {
        $openSessions = $this->openSessions();
        $holdCount = (int) collect($openSessions)->sum('hold_count');

        return [
            'open_sessions' => $openSessions,
            'open_hold_count' => $holdCount,
            'today' => $this->todaySales(),
            'low_stock' => $this->lowTrackedStock(),
            'draft_journal_count' => $this->draftJournalCount(),
        ];
    }

    /**
     * @return list<array{id: int, session_number: string, cashier_name: string, warehouse_name: string, hold_count: int}>
     */
    private function openSessions(): array
    {
        $rows = DB::table('pos_sessions as s')
            ->leftJoin('users as u', 'u.id', '=', 's.opened_by')
            ->leftJoin('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->where('s.status', PosSessionStatus::Open->value)
            ->orderBy('s.id')
            ->get([
                's.id',
                's.session_number',
                'u.name as cashier_name',
                'w.name as warehouse_name',
            ]);

        $holdCounts = DB::table('pos_session_holds as h')
            ->join('pos_sessions as s', 's.id', '=', 'h.pos_session_id')
            ->where('s.status', PosSessionStatus::Open->value)
            ->whereNull('h.taken_at')
            ->groupBy('h.pos_session_id')
            ->select('h.pos_session_id', DB::raw('count(*) as hold_count'))
            ->pluck('hold_count', 'pos_session_id');

        return $rows->map(function (object $row) use ($holdCounts): array {
            return [
                'id' => (int) $row->id,
                'session_number' => (string) $row->session_number,
                'cashier_name' => (string) ($row->cashier_name ?: ''),
                'warehouse_name' => (string) ($row->warehouse_name ?: ''),
                'hold_count' => (int) ($holdCounts[$row->id] ?? 0),
            ];
        })->all();
    }

    /**
     * @return array{sale_count: int, omzet_amount: int, last_sale_number: string|null, last_sold_at: string|null}
     */
    private function todaySales(): array
    {
        $today = now()->toDateString();

        $totals = DB::table('pos_sales')
            ->where('status', PosSaleStatus::Completed->value)
            ->whereDate('sold_at', $today)
            ->selectRaw('count(*) as sale_count, coalesce(sum(payable_amount), 0) as omzet_amount')
            ->first();

        $last = DB::table('pos_sales')
            ->where('status', PosSaleStatus::Completed->value)
            ->whereDate('sold_at', $today)
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->first(['sale_number', 'sold_at']);

        return [
            'sale_count' => (int) ($totals->sale_count ?? 0),
            'omzet_amount' => (int) ($totals->omzet_amount ?? 0),
            'last_sale_number' => $last->sale_number ?? null,
            'last_sold_at' => $last->sold_at ?? null,
        ];
    }

    /**
     * @return list<array{product_id: int, sku: string, name: string, quantity: int}>
     */
    private function lowTrackedStock(): array
    {
        return DB::table('product_stocks as ps')
            ->join('products as p', 'p.id', '=', 'ps.product_id')
            ->where('p.track_inventory', true)
            ->where('p.is_sellable', true)
            ->where('p.is_active', true)
            ->where('ps.quantity', '<', self::LOW_STOCK_BELOW)
            ->orderBy('ps.quantity')
            ->orderBy('p.name')
            ->limit(8)
            ->get([
                'p.id as product_id',
                'p.sku',
                'p.name',
                'ps.quantity',
            ])
            ->map(fn (object $row): array => [
                'product_id' => (int) $row->product_id,
                'sku' => (string) $row->sku,
                'name' => (string) $row->name,
                'quantity' => (int) $row->quantity,
            ])
            ->all();
    }

    private function draftJournalCount(): int
    {
        return (int) DB::table('journal_entries')
            ->where('is_posted', false)
            ->whereNull('deleted_at')
            ->count();
    }
}
