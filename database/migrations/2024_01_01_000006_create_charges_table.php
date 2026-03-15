<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('charge_type')->default('ac'); // ac, dc, supercharger
            $table->double('energy_added_kwh')->nullable();
            $table->double('cost')->nullable();
            $table->integer('start_battery_level')->nullable();
            $table->integer('end_battery_level')->nullable();
            $table->double('start_rated_range')->nullable();
            $table->double('end_rated_range')->nullable();
            $table->double('max_charger_power')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('place_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tag')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'started_at']);
        });

        Schema::create('charge_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('timestamp');
            $table->integer('battery_level')->nullable();
            $table->double('charger_power_kw')->nullable();
            $table->integer('voltage')->nullable();
            $table->integer('current')->nullable();
            $table->double('rated_range')->nullable();

            $table->index(['charge_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_points');
        Schema::dropIfExists('charges');
    }
};
