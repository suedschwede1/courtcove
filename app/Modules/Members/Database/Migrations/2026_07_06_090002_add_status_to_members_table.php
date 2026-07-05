<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('status')->default('aktiv')->after('left_at');
        });

        // Backfill: any existing departed member (left_at already set) is ausgetreten.
        \DB::table('members')->whereNotNull('left_at')->update(['status' => 'ausgetreten']);
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
