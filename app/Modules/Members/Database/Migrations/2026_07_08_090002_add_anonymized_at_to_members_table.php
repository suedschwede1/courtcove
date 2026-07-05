<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('members', 'anonymized_at')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });
    }
};
