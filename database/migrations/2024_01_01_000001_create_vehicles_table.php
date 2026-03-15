<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tesla_vehicle_id')->nullable();
            $table->string('vin')->nullable();
            $table->string('name');
            $table->string('model')->nullable();
            $table->string('trim')->nullable();
            $table->string('color')->nullable();
            $table->string('firmware_version')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('tesla_access_token')->nullable();
            $table->text('tesla_refresh_token')->nullable();
            $table->timestamp('tesla_token_expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
