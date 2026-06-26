<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_squares_coupons', function (Blueprint $table) {
            $table->integer('scid')->autoIncrement();
            $table->integer('sid')->index();
            $table->string('code', 64)->unique();
            $table->string('type', 32)->default('percent');
            $table->integer('value')->default(0);
            $table->integer('valid_from')->default(0);
            $table->integer('valid_until')->default(0);
            $table->integer('usage_max')->default(0);
            $table->integer('usage_count')->default(0);
            $table->string('status', 32)->default('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_squares_coupons');
    }
};
