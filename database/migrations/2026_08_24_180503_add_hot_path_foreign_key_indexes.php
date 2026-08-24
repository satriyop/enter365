<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<array{0: string, 1: string}>
     */
    private array $indexes = [
        ['journal_entry_lines', 'journal_entry_id'],
        ['invoice_items', 'invoice_id'],
        ['bill_items', 'bill_id'],
        ['quotation_items', 'quotation_id'],
        ['purchase_order_items', 'purchase_order_id'],
        ['goods_receipt_note_items', 'goods_receipt_note_id'],
        ['pos_sales', 'pos_session_id'],
        ['pos_sale_items', 'pos_sale_id'],
        ['pos_sale_items', 'product_id'],
        ['pos_sale_tenders', 'pos_sale_id'],
        ['pos_session_holds', 'pos_session_id'],
        ['pos_checkout_idempotencies', 'pos_sale_id'],
        ['pos_sessions', 'warehouse_id'],
        ['pos_sessions', 'opened_by'],
        ['pos_sessions', 'cash_account_id'],
        ['pos_sessions', 'qris_account_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            if ($this->hasIndexOnColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->index($column);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, $column]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $name = $table.'_'.$column.'_index';
            if (! $this->hasIndexNamed($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropIndex($name);
            });
        }
    }

    private function hasIndexOnColumn(string $table, string $column): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['columns'] ?? []) === [$column]) {
                return true;
            }
        }

        return false;
    }

    private function hasIndexNamed(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }
};
