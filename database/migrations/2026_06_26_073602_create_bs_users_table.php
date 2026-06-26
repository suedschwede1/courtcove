<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_users', function (Blueprint $table) {
            $table->integer('uid')->autoIncrement();
            $table->string('name', 64);
            $table->string('email', 128)->unique();
            $table->string('password', 255)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('roles', 255)->nullable();
            $table->string('permissions', 255)->nullable();
            $table->string('status', 32)->default('enabled');
            $table->string('token', 128)->nullable()->unique();
            $table->integer('created')->default(0);
            $table->integer('updated')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_users');
    }
};
