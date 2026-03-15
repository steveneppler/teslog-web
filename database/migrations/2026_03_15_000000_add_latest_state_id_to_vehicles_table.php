<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedBigInteger('latest_state_id')->nullable()->after('firmware_version');
        });

        // Backfill from existing data
        DB::statement('
            UPDATE vehicles SET latest_state_id = (
                SELECT id FROM vehicle_states
                WHERE vehicle_states.vehicle_id = vehicles.id
                ORDER BY timestamp DESC
                LIMIT 1
            )
        ');
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('latest_state_id');
        });
    }
};
