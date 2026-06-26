<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_bookings', function (Blueprint $table) {
            $table->integer('bid')->autoIncrement();
            $table->integer('uid')->index();
            $table->integer('sid')->index();
            $table->string('status', 32)->default('enabled');
            $table->string('status_billing', 32)->default('pending');
            $table->string('visibility', 32)->default('public');
            $table->integer('quantity')->default(1);
            $table->integer('created')->default(0);
            $table->integer('updated')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_bookings');
    }
};
