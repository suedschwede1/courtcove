<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_events', function (Blueprint $table) {
            $table->integer('eid')->autoIncrement();
            $table->integer('sid')->index();
            $table->integer('datetime_start')->default(0);
            $table->integer('datetime_end')->default(0);
            $table->integer('capacity')->default(0);
            $table->string('status', 32)->default('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_events');
    }
};
