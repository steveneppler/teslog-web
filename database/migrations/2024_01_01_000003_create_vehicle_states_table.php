<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->timestamp('timestamp');
            $table->string('state'); // driving, charging, idle, sleeping, offline
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->double('heading')->nullable();
            $table->double('elevation')->nullable();
            $table->double('speed')->nullable();
            $table->integer('power')->nullable();
            $table->integer('battery_level')->nullable();
            $table->double('rated_range')->nullable();
            $table->double('ideal_range')->nullable();
            $table->double('odometer')->nullable();
            $table->double('inside_temp')->nullable();
            $table->double('outside_temp')->nullable();
            $table->boolean('locked')->nullable();
            $table->boolean('sentry_mode')->nullable();
            $table->boolean('climate_on')->nullable();
            $table->string('gear')->nullable();
            $table->integer('charger_power')->nullable();
            $table->integer('charger_voltage')->nullable();
            $table->integer('charger_current')->nullable();
            $table->integer('charge_limit_soc')->nullable();
            $table->string('charge_state')->nullable();
            $table->double('energy_remaining')->nullable();
            $table->string('software_version')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['vehicle_id', 'timestamp']);
            $table->index(['vehicle_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_states');
    }
};
