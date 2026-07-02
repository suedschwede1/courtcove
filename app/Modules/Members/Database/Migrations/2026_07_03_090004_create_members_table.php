<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('member_number')->unique();
            $table->string('firstname', 128);
            $table->string('lastname', 128);
            $table->date('birthdate')->nullable();
            $table->string('email', 128)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('address', 255)->nullable();
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
