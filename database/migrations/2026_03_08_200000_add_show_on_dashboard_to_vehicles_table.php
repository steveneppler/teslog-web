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
            $table->boolean('show_on_dashboard')->default(true)->after('is_active');
            $table->index(['user_id', 'show_on_dashboard']);
        });

        // Default manual/archived vehicles (no tesla_vehicle_id) to hidden
        DB::table('vehicles')->whereNull('tesla_vehicle_id')->update(['show_on_dashboard' => false]);
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('show_on_dashboard');
        });
    }
};
