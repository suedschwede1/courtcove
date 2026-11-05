<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->date('faellig_am')->nullable()->after('ends_on');
        });

        // Backfill: existing fiscal years default their due date to their own start date.
        DB::table('fiscal_years')->whereNull('faellig_am')->update(['faellig_am' => DB::raw('starts_on')]);
    }

    public function down(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropColumn('faellig_am');
        });
    }
};
