<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a payment (entered on the paying member's page) to the household due it settles; the
 * household counterpart of payment_dues.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('household_due_payment')) {
            return;
        }

        Schema::create('household_due_payment', function (Blueprint $table) {
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_due_id')->constrained('household_dues')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['payment_id', 'household_due_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_due_payment');
    }
};
