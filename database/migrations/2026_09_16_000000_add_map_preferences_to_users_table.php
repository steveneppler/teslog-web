<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('map_provider')->nullable()->default(null)->after('theme');
            // Encrypted at rest, so this holds a ciphertext payload rather than
            // the key itself and needs more room than the key's own length.
            $table->text('carto_api_key')->nullable()->default(null)->after('map_provider');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['map_provider', 'carto_api_key']);
        });
    }
};
