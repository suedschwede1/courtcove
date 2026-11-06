<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a fee category bills: 'member' (one due per member, as before) or 'household' (one flat due
 * per household and fiscal year). Existing categories become 'member', so nothing changes for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fee_categories', 'billing_unit')) {
            return;
        }

        Schema::table('fee_categories', function (Blueprint $table) {
            $table->string('billing_unit', 16)->default('member')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('fee_categories', function (Blueprint $table) {
            $table->dropColumn('billing_unit');
        });
    }
};
