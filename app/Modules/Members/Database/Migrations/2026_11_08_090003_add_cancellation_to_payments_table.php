<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Zahlung stornieren": payments are never deleted; a storno is recorded on the payment (when, by whom,
 * optional reason). A cancelled payment no longer pays its dues (MemberDue/HouseholdDue::payments() only see
 * uncancelled payments); the links in payment_dues / household_due_payment stay as history. Additive, guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'cancelled_at')) {
                $table->dateTime('cancelled_at')->nullable();
            }
            if (! Schema::hasColumn('payments', 'cancelled_by')) {
                $table->unsignedInteger('cancelled_by')->nullable();
            }
            if (! Schema::hasColumn('payments', 'cancel_reason')) {
                $table->string('cancel_reason', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            foreach (['cancel_reason', 'cancelled_by', 'cancelled_at'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
