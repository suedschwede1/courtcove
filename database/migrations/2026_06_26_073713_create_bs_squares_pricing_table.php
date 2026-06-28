<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_squares_pricing')) {
            return;
        }
        Schema::create('bs_squares_pricing', function (Blueprint $table) {
            $table->increments('spid');
            $table->unsignedInteger('sid')->nullable()->index();
            $table->unsignedInteger('priority');
            $table->date('date_start');
            $table->date('date_end');
            $table->unsignedTinyInteger('day_start')->nullable();
            $table->unsignedTinyInteger('day_end')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->unsignedInteger('rate')->nullable();
            $table->boolean('gross')->nullable();
            $table->unsignedInteger('per_time_block')->nullable();
            $table->boolean('per_quantity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_squares_pricing');
    }
};
