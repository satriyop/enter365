<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('solar_proposals', function (Blueprint $table) {
            // Roof polygon for storing GeoJSON drawn on the map
            $table->json('roof_polygon')->nullable()->after('roof_area_m2');

            // Public token for customer portal access (shareable link)
            $table->uuid('public_token')->nullable()->unique()->after('status');
            $table->timestamp('public_token_expires_at')->nullable()->after('public_token');
        });

        // Update status check constraint to include 'converted' (only for databases that support it)
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE solar_proposals DROP CONSTRAINT IF EXISTS solar_proposals_status_check');
            DB::statement("ALTER TABLE solar_proposals ADD CONSTRAINT solar_proposals_status_check CHECK (status IN ('draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'))");
        } elseif ($driver === 'mysql') {
            // MySQL 8.0.16+ supports CHECK constraints, but older versions ignore them
            // The constraint may not exist, so we use IF EXISTS-like workaround via procedures or simply skip
            // For simplicity, rely on application-level validation for MySQL
        }
        // SQLite: CHECK constraints in SQLite are part of the table definition and cannot be modified via ALTER TABLE
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore original status check constraint (only for databases that support it)
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE solar_proposals DROP CONSTRAINT IF EXISTS solar_proposals_status_check');
            DB::statement("ALTER TABLE solar_proposals ADD CONSTRAINT solar_proposals_status_check CHECK (status IN ('draft', 'sent', 'accepted', 'rejected', 'expired'))");
        }

        Schema::table('solar_proposals', function (Blueprint $table) {
            $table->dropColumn(['roof_polygon', 'public_token', 'public_token_expires_at']);
        });
    }
};
