<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_events_meta')) {
            return;
        }
        Schema::create('bs_events_meta', function (Blueprint $table) {
            $table->increments('emid');
            $table->unsignedInteger('eid')->index();
            $table->string('key', 64)->index();
            $table->text('value');
            $table->string('locale', 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_events_meta');
    }
};
