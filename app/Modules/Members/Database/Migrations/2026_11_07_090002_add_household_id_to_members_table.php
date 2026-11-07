<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member belongs to at most one household (single nullable column). Existing members keep NULL;
 * written only through the household service, never mass-assigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('members', 'household_id')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('household_id')->nullable()->after('fee_category_id')->index()->constrained('member_households')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['household_id']);
            $table->dropIndex(['household_id']);
            $table->dropColumn('household_id');
        });
    }
};
