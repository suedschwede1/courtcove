<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_role_mail_category', function (Blueprint $table) {
            $table->foreignId('club_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mail_category_id')->constrained('mail_categories')->cascadeOnDelete();
            $table->primary(['club_role_id', 'mail_category_id']);
        });

        DB::table('club_roles')
            ->whereNotNull('mail_category_id')
            ->select('id', 'mail_category_id')
            ->get()
            ->each(function (object $role): void {
                DB::table('club_role_mail_category')->insert([
                    'club_role_id' => $role->id,
                    'mail_category_id' => $role->mail_category_id,
                ]);
            });

        Schema::table('club_roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mail_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('club_roles', function (Blueprint $table) {
            $table->foreignId('mail_category_id')->nullable()->after('is_board')
                ->constrained('mail_categories')->nullOnDelete();
        });

        DB::table('club_role_mail_category')
            ->select('club_role_id', 'mail_category_id')
            ->get()
            ->groupBy('club_role_id')
            ->each(function ($rows, $clubRoleId): void {
                DB::table('club_roles')->where('id', $clubRoleId)
                    ->update(['mail_category_id' => $rows->first()->mail_category_id]);
            });

        Schema::dropIfExists('club_role_mail_category');
    }
};
