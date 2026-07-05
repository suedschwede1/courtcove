<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No DB-level foreign key here: bs_users.uid is `int unsigned` while this
     * column is `bigint unsigned` (already created that way before this
     * migration ran against the shared dev DB), and MySQL requires matching
     * column types for a FK. Referential integrity is enforced in the
     * application layer instead (see MemberController's `exists:bs_users,uid`
     * validation), consistent with how the rest of this legacy schema already
     * links to bs_users without FK constraints.
     *
     * The unique-index add is wrapped in try/catch (rather than an
     * information_schema/SHOW INDEX check) so this migration works on both
     * MySQL (the shared dev DB, where the column and index may already exist
     * from concurrent work) and SQLite (the test suite's fresh in-memory DB).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('members', 'user_id')) {
            Schema::table('members', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('nuliga_id');
            });
        }

        try {
            Schema::table('members', function (Blueprint $table) {
                $table->unique('user_id');
            });
        } catch (\Throwable) {
            // Index already exists (e.g. added by concurrent work on the shared dev DB).
        }
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }
};
