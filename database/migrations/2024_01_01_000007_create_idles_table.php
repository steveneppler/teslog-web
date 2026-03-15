<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('place_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('start_battery_level')->nullable();
            $table->integer('end_battery_level')->nullable();
            $table->double('vampire_drain_rate')->nullable();
            $table->boolean('sentry_mode_active')->default(false);
            $table->timestamps();

            $table->index(['vehicle_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idles');
    }
};
