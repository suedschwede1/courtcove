<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_squares', function (Blueprint $table) {
            $table->integer('sid')->autoIncrement();
            $table->string('name', 64);
            $table->string('alias', 64)->nullable();
            $table->string('status', 32)->default('enabled');
            $table->integer('capacity')->default(0);
            $table->integer('capacity_heterogenic')->default(0);
            $table->integer('time_start')->default(0);
            $table->integer('time_end')->default(86400);
            $table->integer('time_block')->default(3600);
            $table->integer('time_block_bookable')->default(0);
            $table->integer('time_block_bookable_max')->default(0);
            $table->integer('min_range_book')->default(0);
            $table->integer('range_book')->default(0);
            $table->integer('range_cancel')->default(0);
            $table->integer('priority')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_squares');
    }
};
