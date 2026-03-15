<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->double('distance')->nullable();
            $table->double('energy_used_kwh')->nullable();
            $table->double('efficiency')->nullable();
            $table->double('start_latitude')->nullable();
            $table->double('start_longitude')->nullable();
            $table->double('end_latitude')->nullable();
            $table->double('end_longitude')->nullable();
            $table->string('start_address')->nullable();
            $table->string('end_address')->nullable();
            $table->foreignId('start_place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->foreignId('end_place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->integer('start_battery_level')->nullable();
            $table->integer('end_battery_level')->nullable();
            $table->double('start_rated_range')->nullable();
            $table->double('end_rated_range')->nullable();
            $table->double('start_odometer')->nullable();
            $table->double('end_odometer')->nullable();
            $table->double('max_speed')->nullable();
            $table->double('avg_speed')->nullable();
            $table->double('outside_temp_avg')->nullable();
            $table->string('tag')->nullable();
            $table->text('notes')->nullable();
            $table->json('weather')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'started_at']);
            $table->index('tag');
        });

        Schema::create('drive_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drive_id')->constrained()->cascadeOnDelete();
            $table->timestamp('timestamp');
            $table->double('latitude');
            $table->double('longitude');
            $table->double('altitude')->nullable();
            $table->double('speed')->nullable();
            $table->integer('power')->nullable();
            $table->integer('battery_level')->nullable();
            $table->double('heading')->nullable();

            $table->index(['drive_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_points');
        Schema::dropIfExists('drives');
    }
};
