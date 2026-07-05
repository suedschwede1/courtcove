<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_sends', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('sent_by_user_id')->nullable();
            $table->string('audience_key', 128);
            $table->string('audience_label', 255);
            $table->unsignedInteger('recipient_count')->default(0);
            $table->string('from_name', 128);
            $table->string('from_address', 128);
            $table->string('subject', 255);
            $table->text('body');
            $table->timestamps();

            $table->index('sent_by_user_id');
            $table->index('audience_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_sends');
    }
};
