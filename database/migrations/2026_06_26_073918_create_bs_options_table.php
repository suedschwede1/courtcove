<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real booking_local schema. Global config as key/value, optionally per-locale.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_options')) {
            return;
        }
        Schema::create('bs_options', function (Blueprint $table) {
            $table->increments('oid');
            $table->string('key', 64)->index();
            $table->text('value');
            $table->string('locale', 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_options');
    }
};
