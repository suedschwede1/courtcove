<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Households (family fee): a grouping of members that can be billed with one household due per
 * fiscal year. Additive: nothing creates a household automatically, there is no backfill.
 * name NULL = the label is computed from the members' last names; fee_category_id NULL = only a
 * grouping (members billed individually); households are dissolved (dissolved_at), never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_households')) {
            return;
        }

        Schema::create('member_households', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->nullable();
            $table->foreignId('fee_category_id')->nullable()->constrained('fee_categories')->nullOnDelete();
            $table->foreignId('payer_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamp('dissolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_households');
    }
};
