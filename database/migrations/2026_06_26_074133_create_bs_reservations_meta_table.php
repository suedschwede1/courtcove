<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_reservations_meta')) {
            return;
        }
        Schema::create('bs_reservations_meta', function (Blueprint $table) {
            $table->increments('rmid');
            $table->unsignedInteger('rid')->index();
            $table->string('key', 64)->index();
            $table->text('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_reservations_meta');
    }
};
