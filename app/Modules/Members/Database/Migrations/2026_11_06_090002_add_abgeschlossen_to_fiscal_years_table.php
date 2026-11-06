<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closing a business year: when (timestamp) and by whom (bs_users.uid, no foreign key, like
 * member_changes.admin_user_id). Both NULL = the year is open. Additive and nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fiscal_years', 'abgeschlossen_am')) {
            Schema::table('fiscal_years', function (Blueprint $table) {
                $table->timestamp('abgeschlossen_am')->nullable()->after('faellig_am');
            });
        }

        if (! Schema::hasColumn('fiscal_years', 'abgeschlossen_von')) {
            Schema::table('fiscal_years', function (Blueprint $table) {
                $table->unsignedInteger('abgeschlossen_von')->nullable()->after('abgeschlossen_am');
            });
        }
    }

    public function down(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropColumn(['abgeschlossen_am', 'abgeschlossen_von']);
        });
    }
};
