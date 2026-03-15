<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_raw', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->timestamp('timestamp');
            $table->string('field_name');
            $table->double('value_numeric')->nullable();
            $table->string('value_string')->nullable();
            $table->boolean('processed')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vehicle_id', 'timestamp']);
            $table->index(['vehicle_id', 'processed']);
            $table->index(['vehicle_id', 'field_name', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_raw');
    }
};
