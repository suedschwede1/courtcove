<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The only additive change to the adopted pyramid tables: the email column used
 * for the member match (schema step 20260806 of the old application). Guarded so
 * it is a no-op where the column already exists or the table is absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bs_pyramid_players')) {
            return;
        }

        if (! Schema::hasColumn('bs_pyramid_players', 'email')) {
            Schema::table('bs_pyramid_players', function (Blueprint $table): void {
                $table->string('email', 128)->nullable()->after('phone');
            });
        }

        if (Schema::hasTable('bs_pyramid_schema_versions')) {
            DB::table('bs_pyramid_schema_versions')->insertOrIgnore([
                'version' => 20260806,
                'applied' => now()->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables and columns are never dropped by Laravel.
    }
};
