<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchase_orders', 'invoices', 'bills', 'purchase_returns', 'delivery_orders', 'sales_returns'] as $table) {
            $this->addUniqueConstraint($table);
        }
    }

    public function down(): void
    {
        foreach (['purchase_orders', 'invoices', 'bills', 'purchase_returns', 'delivery_orders', 'sales_returns'] as $table) {
            $this->removeUniqueConstraint($table);
        }
    }

    private function addUniqueConstraint(string $table): void
    {
        $columns = match ($table) {
            'purchase_orders' => ['po_number'],
            'invoices' => ['invoice_number'],
            'bills' => ['bill_number'],
            'purchase_returns', 'sales_returns' => ['return_number'],
            'delivery_orders' => ['do_number'],
            default => [],
        };

        foreach ($columns as $column) {
            try {
                $indexName = $table.'_'.$column.'_unique';
                DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS `{$indexName}` ON `{$table}` (`{$column}`)");
            } catch (\Exception $e) {
                // Index may already exist, continue
            }
        }
    }

    private function removeUniqueConstraint(string $table): void
    {
        $columns = match ($table) {
            'purchase_orders' => ['po_number'],
            'invoices' => ['invoice_number'],
            'bills' => ['bill_number'],
            'purchase_returns', 'sales_returns' => ['return_number'],
            'delivery_orders' => ['do_number'],
            default => [],
        };

        foreach ($columns as $column) {
            try {
                $indexName = $table.'_'.$column.'_unique';
                DB::statement("DROP INDEX IF EXISTS `{$indexName}`");
            } catch (\Exception $e) {
                // Index may not exist, continue
            }
        }
    }
};
