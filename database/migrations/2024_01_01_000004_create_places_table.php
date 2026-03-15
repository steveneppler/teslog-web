<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->double('latitude');
            $table->double('longitude');
            $table->integer('radius_meters')->default(50);
            $table->double('electricity_cost_per_kwh')->nullable();
            $table->string('auto_tag')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('place_tou_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('day_of_week'); // 0=Sunday, 6=Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->double('rate_per_kwh');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_tou_rates');
        Schema::dropIfExists('places');
    }
};
