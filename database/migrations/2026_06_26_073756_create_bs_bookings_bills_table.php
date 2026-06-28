<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_bookings_bills')) {
            return;
        }
        Schema::create('bs_bookings_bills', function (Blueprint $table) {
            $table->increments('bbid');
            $table->unsignedInteger('bid')->index();
            $table->string('description', 512);
            $table->unsignedInteger('quantity')->nullable();
            $table->unsignedInteger('time')->nullable();
            $table->integer('price');
            $table->unsignedInteger('rate');
            $table->boolean('gross');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_bookings_bills');
    }
};
