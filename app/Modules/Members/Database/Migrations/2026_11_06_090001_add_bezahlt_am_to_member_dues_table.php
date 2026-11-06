<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Bezahlt am": the date a due was settled (the payment date, or the date an admin marked it paid).
 * Additive and nullable; existing rows are filled by `members:backfill-paid-at`, not here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('member_dues', 'bezahlt_am')) {
            return;
        }

        Schema::table('member_dues', function (Blueprint $table) {
            $table->date('bezahlt_am')->nullable()->after('faellig_am');
        });
    }

    public function down(): void
    {
        Schema::table('member_dues', function (Blueprint $table) {
            $table->dropColumn('bezahlt_am');
        });
    }
};
