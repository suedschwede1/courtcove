<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: the permission of a user (member / assist / admin), separate from the
 * account status. Only read when config users.split_role is on; the old system
 * neither maps nor inserts it, so the default keeps its INSERTs working.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bs_users', 'role')) {
            return;
        }

        Schema::table('bs_users', function (Blueprint $table): void {
            $table->string('role', 16)->default('member')->after('status');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('bs_users', 'role')) {
            Schema::table('bs_users', fn (Blueprint $table) => $table->dropColumn('role'));
        }
    }
};
