<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fee categories are deactivated instead of deleted: NULL = active. Additive and nullable;
 * existing dues, rates and member assignments are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fee_categories', 'deactivated_at')) {
            return;
        }

        Schema::table('fee_categories', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('fee_categories', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};
