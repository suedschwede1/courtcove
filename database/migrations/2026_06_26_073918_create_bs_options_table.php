<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_options', function (Blueprint $table) {
            $table->integer('oid')->autoIncrement();
            $table->string('option_key', 64)->unique();
            $table->text('option_value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_options');
    }
};
