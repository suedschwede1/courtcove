<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anschlussmitglied: the member this member is connected to (its Vollzahler). Additive and nullable; existing members
 * keep NULL. Written only by MemberConnectionService, never mass-assigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('members', 'connected_to_member_id')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('connected_to_member_id')->nullable()->after('household_id')->index()->constrained('members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['connected_to_member_id']);
            $table->dropIndex(['connected_to_member_id']);
            $table->dropColumn('connected_to_member_id');
        });
    }
};
