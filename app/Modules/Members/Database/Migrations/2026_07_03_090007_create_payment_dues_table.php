<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_due_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['payment_id', 'member_due_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_dues');
    }
};
