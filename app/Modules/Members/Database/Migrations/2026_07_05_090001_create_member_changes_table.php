<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('admin_user_id')->nullable()->index();
            $table->foreign('admin_user_id')->references('uid')->on('bs_users')->nullOnDelete();
            $table->string('action', 16);
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_changes');
    }
};
