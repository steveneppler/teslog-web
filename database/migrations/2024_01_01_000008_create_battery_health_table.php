<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battery_health', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_at');
            $table->integer('battery_level');
            $table->double('rated_range');
            $table->double('ideal_range')->nullable();
            $table->double('degradation_pct')->nullable();
            $table->timestamps();

            $table->unique(['vehicle_id', 'recorded_at']);
        });

        Schema::create('firmware_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->timestamp('detected_at');
            $table->string('previous_version')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firmware_history');
        Schema::dropIfExists('battery_health');
    }
};
