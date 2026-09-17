<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Several providers need their own key, so a single CARTO column no longer fits.
 * Keys move into one encrypted provider => key map, which also lets a user keep a
 * key for a provider they are not currently using.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('map_api_keys')->nullable()->default(null)->after('map_provider');
        });

        foreach (DB::table('users')->whereNotNull('carto_api_key')->get(['id', 'carto_api_key']) as $user) {
            $key = self::decrypt($user->carto_api_key);

            if ($key === null) {
                continue;
            }

            DB::table('users')->where('id', $user->id)->update([
                'map_api_keys' => Crypt::encryptString(json_encode(['carto' => $key])),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('carto_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('carto_api_key')->nullable()->default(null)->after('map_provider');
        });

        foreach (DB::table('users')->whereNotNull('map_api_keys')->get(['id', 'map_api_keys']) as $user) {
            $keys = json_decode((string) self::decrypt($user->map_api_keys), true);

            if (! isset($keys['carto'])) {
                continue;
            }

            DB::table('users')->where('id', $user->id)->update([
                'carto_api_key' => Crypt::encryptString($keys['carto']),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('map_api_keys');
        });
    }

    /**
     * A key encrypted under a since-rotated APP_KEY cannot be recovered, and losing
     * one is not a reason to fail the schema change — the user can re-enter it.
     */
    private static function decrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }
};
