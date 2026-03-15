<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('command');
            $table->json('parameters')->nullable();
            $table->boolean('success');
            $table->string('error_message')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index(['vehicle_id', 'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_logs');
    }
};
