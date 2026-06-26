<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_squares_pricing', function (Blueprint $table) {
            $table->integer('sprid')->autoIncrement();
            $table->integer('sid')->index();
            $table->integer('spid')->index();
            $table->integer('date_start')->default(0);
            $table->integer('date_end')->default(0);
            $table->integer('time_start')->default(0);
            $table->integer('time_end')->default(86400);
            $table->integer('price')->default(0);
            $table->integer('priority')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_squares_pricing');
    }
};
