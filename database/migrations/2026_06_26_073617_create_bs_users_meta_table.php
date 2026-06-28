<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_users_meta')) {
            return;
        }
        Schema::create('bs_users_meta', function (Blueprint $table) {
            $table->increments('umid');
            $table->unsignedInteger('uid')->index();
            $table->string('key', 64)->index();
            $table->text('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_users_meta');
    }
};
