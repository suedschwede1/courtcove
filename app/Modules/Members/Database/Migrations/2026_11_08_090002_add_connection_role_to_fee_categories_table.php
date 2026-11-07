<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Role of a fee category for the Anschlussmitglied rule: 'vollzahler' (may have an Anschlussmitglied) or 'anschluss'
 * (the reduced category of the connected partner); NULL for every other category. Additive. The two category names the
 * CSV import uses are marked once, only where no role is set yet; no category is created or renamed.
 */
return new class extends Migration
{
    private const KNOWN = [
        'Vollzahler' => 'vollzahler',
        'Ehepartner/in, Lebensgefährte/in' => 'anschluss',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('fee_categories', 'connection_role')) {
            Schema::table('fee_categories', function (Blueprint $table) {
                $table->string('connection_role', 16)->nullable()->after('billing_unit');
            });
        }

        foreach (self::KNOWN as $name => $role) {
            DB::table('fee_categories')->where('name', $name)->whereNull('connection_role')->update(['connection_role' => $role]);
        }
    }

    public function down(): void
    {
        Schema::table('fee_categories', function (Blueprint $table) {
            $table->dropColumn('connection_role');
        });
    }
};
