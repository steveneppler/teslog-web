<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->json('telemetry_fields')->nullable();
        });
        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->longText('value_string')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('telemetry_fields');
        });
        // Keep text values: shrinking this column would truncate collected routes.
    }
};
