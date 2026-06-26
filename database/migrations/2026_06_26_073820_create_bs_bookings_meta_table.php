<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_bookings_meta', function (Blueprint $table) {
            $table->integer('bmid')->autoIncrement();
            $table->integer('bid')->index();
            $table->string('meta_key', 64)->index();
            $table->text('meta_value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_bookings_meta');
    }
};
