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
            $table->increments('scid');
            $table->unsignedInteger('sid')->nullable()->index();
            $table->string('code', 64)->index();
            $table->dateTime('date_start')->nullable();
            $table->dateTime('date_end')->nullable();
            $table->unsignedInteger('discount_for_booking');
            $table->unsignedInteger('discount_for_products');
            $table->boolean('discount_in_percent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_squares_coupons');
    }
};
