<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_bookings_bills', function (Blueprint $table) {
            $table->integer('bbid')->autoIncrement();
            $table->integer('bid')->index();
            $table->integer('spid')->nullable()->index();
            $table->integer('price')->default(0);
            $table->string('description', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_bookings_bills');
    }
};
