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
        ['inventory_movements', 'warehouse_id'],
        ['inventory_cost_layers', 'warehouse_id'],
        ['product_stocks', 'warehouse_id'],
        ['payments', 'journal_entry_id'],
        ['invoices', 'contact_id'],
        ['bills', 'contact_id'],
        ['journal_entries', 'fiscal_period_id'],
        ['pos_sales', 'cogs_journal_entry_id'],
        ['pos_sales', 'created_by'],
        ['pos_sales', 'journal_entry_id'],
        ['pos_sales', 'voided_by'],
        ['pos_sale_items', 'inventory_movement_id'],
        ['pos_sessions', 'closed_by'],
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
