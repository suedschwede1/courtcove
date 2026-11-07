<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One flat due per household and fiscal year; status columns mirror member_dues so the shared
 * status rules apply (including "Bezahlt am", added to member_dues by the dues work). A household
 * with dues cannot be deleted (restrict): history stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('household_dues')) {
            return;
        }

        Schema::create('household_dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('member_households')->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_category_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 8, 2);
            $table->string('status')->default('ausstehend');
            $table->decimal('teilbetrag', 8, 2)->nullable();
            $table->text('kommentar')->nullable();
            $table->foreignId('bezahlt_bis_fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->date('faellig_am')->nullable();
            $table->date('bezahlt_am')->nullable();
            $table->timestamps();
            $table->unique(['household_id', 'fiscal_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_dues');
    }
};
