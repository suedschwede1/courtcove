<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('login_history')) {
            return;
        }

        Schema::create('login_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip', 45);
            $table->string('country_code', 2)->nullable();
            $table->string('status', 16);
            $table->string('risk_flags', 128)->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['user_id', 'created_at'], 'login_history_user_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_history');
    }
};
