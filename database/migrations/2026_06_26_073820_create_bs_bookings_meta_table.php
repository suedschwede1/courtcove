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
            $table->increments('bmid');
            $table->unsignedInteger('bid')->index();
            $table->string('key', 64)->index();
            $table->text('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_bookings_meta');
    }
};
