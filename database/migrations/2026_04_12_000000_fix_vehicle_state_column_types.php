<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix charger columns from integer to double — Tesla Fleet Telemetry sends
        // float values (e.g. 1.4 kW AC power, 113.78V). SQLite masks this but
        // MySQL/PostgreSQL would truncate.
        // Also fix battery_level — telemetry sends fractional SOC (e.g. 32.05%).
        Schema::table('vehicle_states', function (Blueprint $table) {
            $table->double('charger_power')->nullable()->change();
            $table->double('charger_voltage')->nullable()->change();
            $table->double('charger_current')->nullable()->change();
            $table->double('battery_level')->nullable()->change();
        });

        Schema::table('drives', function (Blueprint $table) {
            $table->double('start_battery_level')->nullable()->change();
            $table->double('end_battery_level')->nullable()->change();
        });

        Schema::table('drive_points', function (Blueprint $table) {
            $table->double('battery_level')->nullable()->change();
        });

        Schema::table('idles', function (Blueprint $table) {
            $table->double('start_battery_level')->nullable()->change();
            $table->double('end_battery_level')->nullable()->change();
        });

        Schema::table('battery_health', function (Blueprint $table) {
            $table->double('battery_level')->change();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_states', function (Blueprint $table) {
            $table->integer('charger_power')->nullable()->change();
            $table->integer('charger_voltage')->nullable()->change();
            $table->integer('charger_current')->nullable()->change();
            $table->integer('battery_level')->nullable()->change();
        });

        Schema::table('drives', function (Blueprint $table) {
            $table->integer('start_battery_level')->nullable()->change();
            $table->integer('end_battery_level')->nullable()->change();
        });

        Schema::table('drive_points', function (Blueprint $table) {
            $table->integer('battery_level')->nullable()->change();
        });

        Schema::table('idles', function (Blueprint $table) {
            $table->integer('start_battery_level')->nullable()->change();
            $table->integer('end_battery_level')->nullable()->change();
        });

        Schema::table('battery_health', function (Blueprint $table) {
            $table->integer('battery_level')->change();
        });
    }
};
