<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change battery levels from integer to double for decimal precision
        // SQLite doesn't enforce column types, so this is safe without data conversion
        Schema::table('charges', function (Blueprint $table) {
            $table->double('start_battery_level')->nullable()->change();
            $table->double('end_battery_level')->nullable()->change();
            $table->double('energy_used_kwh')->nullable()->after('energy_added_kwh');
            $table->double('charging_efficiency')->nullable()->after('energy_used_kwh');
            $table->double('avg_voltage')->nullable()->after('max_charger_power');
            $table->double('max_voltage')->nullable()->after('avg_voltage');
            $table->double('avg_current')->nullable()->after('max_voltage');
            $table->double('max_current')->nullable()->after('avg_current');
            $table->double('odometer')->nullable()->after('max_current');
        });

        // Change charge_points battery_level, voltage, current to double for precision
        Schema::table('charge_points', function (Blueprint $table) {
            $table->double('battery_level')->nullable()->change();
            $table->double('voltage')->nullable()->change();
            $table->double('current')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->integer('start_battery_level')->nullable()->change();
            $table->integer('end_battery_level')->nullable()->change();
            $table->dropColumn([
                'energy_used_kwh',
                'charging_efficiency',
                'avg_voltage',
                'max_voltage',
                'avg_current',
                'max_current',
                'odometer',
            ]);
        });

        Schema::table('charge_points', function (Blueprint $table) {
            $table->integer('battery_level')->nullable()->change();
            $table->integer('voltage')->nullable()->change();
            $table->integer('current')->nullable()->change();
        });
    }
};
