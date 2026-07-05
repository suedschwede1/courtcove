<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_roles', function (Blueprint $table) {
            $table->foreignId('mail_category_id')->nullable()->after('is_board')
                ->constrained('mail_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('club_roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mail_category_id');
        });
    }
};
