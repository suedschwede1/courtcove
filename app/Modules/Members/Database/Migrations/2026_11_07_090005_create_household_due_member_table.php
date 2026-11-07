<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coverage: which members a household due covers. fiscal_year_id is denormalised so the database
 * itself forbids covering one member twice in the same fiscal year.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('household_due_member')) {
            return;
        }

        Schema::create('household_due_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_due_id')->constrained('household_dues')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['household_due_id', 'member_id']);
            $table->unique(['member_id', 'fiscal_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_due_member');
    }
};
