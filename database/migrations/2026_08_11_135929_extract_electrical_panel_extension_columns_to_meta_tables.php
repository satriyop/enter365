<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase A: move electrical_panel extension FKs off core manufacturing tables
 * into add-on owned meta tables, then drop the core columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electrical_panel_bom_item_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_item_id')->unique()->constrained('bom_items')->cascadeOnDelete();
            $table->foreignId('component_standard_id')->nullable()->constrained('component_standards')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('electrical_panel_bom_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->unique()->constrained('boms')->cascadeOnDelete();
            $table->foreignId('spec_rule_set_id')->nullable()->constrained('spec_validation_rule_sets')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('electrical_panel_bom_template_item_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_template_item_id')->unique()->constrained('bom_template_items')->cascadeOnDelete();
            $table->foreignId('component_standard_id')->nullable()->constrained('component_standards')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('electrical_panel_bom_template_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_template_id')->unique()->constrained('bom_templates')->cascadeOnDelete();
            $table->foreignId('default_rule_set_id')->nullable()->constrained('spec_validation_rule_sets')->nullOnDelete();
            $table->timestamps();
        });

        // Copy existing extension data (if columns still present)
        if (Schema::hasColumn('bom_items', 'component_standard_id')) {
            DB::table('bom_items')
                ->whereNotNull('component_standard_id')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    $now = now();
                    $insert = [];
                    foreach ($rows as $row) {
                        $insert[] = [
                            'bom_item_id' => $row->id,
                            'component_standard_id' => $row->component_standard_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    DB::table('electrical_panel_bom_item_meta')->insert($insert);
                });
        }

        if (Schema::hasColumn('boms', 'spec_rule_set_id')) {
            DB::table('boms')
                ->whereNotNull('spec_rule_set_id')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    $now = now();
                    $insert = [];
                    foreach ($rows as $row) {
                        $insert[] = [
                            'bom_id' => $row->id,
                            'spec_rule_set_id' => $row->spec_rule_set_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    DB::table('electrical_panel_bom_meta')->insert($insert);
                });
        }

        if (Schema::hasColumn('bom_template_items', 'component_standard_id')) {
            DB::table('bom_template_items')
                ->whereNotNull('component_standard_id')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    $now = now();
                    $insert = [];
                    foreach ($rows as $row) {
                        $insert[] = [
                            'bom_template_item_id' => $row->id,
                            'component_standard_id' => $row->component_standard_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    DB::table('electrical_panel_bom_template_item_meta')->insert($insert);
                });
        }

        if (Schema::hasColumn('bom_templates', 'default_rule_set_id')) {
            DB::table('bom_templates')
                ->whereNotNull('default_rule_set_id')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    $now = now();
                    $insert = [];
                    foreach ($rows as $row) {
                        $insert[] = [
                            'bom_template_id' => $row->id,
                            'default_rule_set_id' => $row->default_rule_set_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    DB::table('electrical_panel_bom_template_meta')->insert($insert);
                });
        }

        $this->dropExtensionColumn('bom_items', 'component_standard_id');
        $this->dropExtensionColumn('boms', 'spec_rule_set_id');
        $this->dropExtensionColumn('bom_template_items', 'component_standard_id');
        $this->dropExtensionColumn('bom_templates', 'default_rule_set_id');
    }

    /**
     * Drop a foreignId column + any secondary indexes (SQLite-safe).
     */
    private function dropExtensionColumn(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        // Drop non-primary indexes that include the column first (extra index from original migrations).
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['primary'] ?? false) || ($index['unique'] ?? false)) {
                // unique/foreign may still need dropForeign path below
            }
            if (! in_array($column, $index['columns'] ?? [], true)) {
                continue;
            }
            if ($index['primary'] ?? false) {
                continue;
            }
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($index) {
                    $blueprint->dropIndex($index['name']);
                });
            } catch (\Throwable) {
                // Index may be owned by FK constraint — dropForeign handles it.
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
            try {
                $blueprint->dropForeign([$column]);
            } catch (\Throwable) {
                // SQLite may not name FK the same way; fall through to dropColumn.
            }
            if (Schema::hasColumn($table, $column)) {
                $blueprint->dropColumn($column);
            }
        });
    }

    public function down(): void
    {
        // Restore core columns
        if (! Schema::hasColumn('bom_items', 'component_standard_id')) {
            Schema::table('bom_items', function (Blueprint $table) {
                $table->foreignId('component_standard_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('component_standards')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('boms', 'spec_rule_set_id')) {
            Schema::table('boms', function (Blueprint $table) {
                $table->foreignId('spec_rule_set_id')
                    ->nullable()
                    ->constrained('spec_validation_rule_sets')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('bom_template_items', 'component_standard_id')) {
            Schema::table('bom_template_items', function (Blueprint $table) {
                $table->foreignId('component_standard_id')
                    ->nullable()
                    ->constrained('component_standards')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('bom_templates', 'default_rule_set_id')) {
            Schema::table('bom_templates', function (Blueprint $table) {
                $table->foreignId('default_rule_set_id')
                    ->nullable()
                    ->constrained('spec_validation_rule_sets')
                    ->nullOnDelete();
            });
        }

        // Copy back from meta
        foreach (DB::table('electrical_panel_bom_item_meta')->get() as $row) {
            DB::table('bom_items')->where('id', $row->bom_item_id)->update([
                'component_standard_id' => $row->component_standard_id,
            ]);
        }
        foreach (DB::table('electrical_panel_bom_meta')->get() as $row) {
            DB::table('boms')->where('id', $row->bom_id)->update([
                'spec_rule_set_id' => $row->spec_rule_set_id,
            ]);
        }
        foreach (DB::table('electrical_panel_bom_template_item_meta')->get() as $row) {
            DB::table('bom_template_items')->where('id', $row->bom_template_item_id)->update([
                'component_standard_id' => $row->component_standard_id,
            ]);
        }
        foreach (DB::table('electrical_panel_bom_template_meta')->get() as $row) {
            DB::table('bom_templates')->where('id', $row->bom_template_id)->update([
                'default_rule_set_id' => $row->default_rule_set_id,
            ]);
        }

        Schema::dropIfExists('electrical_panel_bom_template_meta');
        Schema::dropIfExists('electrical_panel_bom_template_item_meta');
        Schema::dropIfExists('electrical_panel_bom_meta');
        Schema::dropIfExists('electrical_panel_bom_item_meta');
    }
};
