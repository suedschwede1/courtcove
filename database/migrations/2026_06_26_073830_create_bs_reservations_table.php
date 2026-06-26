<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_reservations', function (Blueprint $table) {
            $table->integer('rid')->autoIncrement();
            $table->integer('bid')->index();
            $table->integer('date')->index();
            $table->integer('time_start')->default(0);
            $table->integer('time_end')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_reservations');
    }
};
