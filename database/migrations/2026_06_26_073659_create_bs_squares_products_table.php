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
            $table->increments('spid');
            $table->unsignedInteger('sid')->nullable()->index();
            $table->unsignedInteger('priority');
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->string('options', 512);
            $table->unsignedInteger('price');
            $table->unsignedInteger('rate');
            $table->boolean('gross');
            $table->string('locale', 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_squares_products');
    }
};
