<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_squares_products', function (Blueprint $table) {
            $table->integer('spid')->autoIncrement();
            $table->integer('sid')->index();
            $table->string('name', 64);
            $table->string('type', 32)->default('single');
            $table->integer('price')->default(0);
            $table->integer('priority')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_squares_products');
    }
};
