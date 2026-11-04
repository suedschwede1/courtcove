<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = [
            ['bs_users_meta', ['uid', 'key'], 'bs_users_meta_uid_key'],
            ['bs_squares_meta', ['sid', 'key'], 'bs_squares_meta_sid_key'],
            ['bs_bookings_meta', ['bid', 'key'], 'bs_bookings_meta_bid_key'],
            ['bs_events_meta', ['eid', 'key'], 'bs_events_meta_eid_key'],
        ];

        // Guarded so a re-run after a partial failure does not die on "Duplicate key name".
        foreach ($indexes as [$table, $columns, $name]) {
            if (! Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        Schema::table('bs_users_meta', fn (Blueprint $t) => $t->dropIndex('bs_users_meta_uid_key'));
        Schema::table('bs_squares_meta', fn (Blueprint $t) => $t->dropIndex('bs_squares_meta_sid_key'));
        Schema::table('bs_bookings_meta', fn (Blueprint $t) => $t->dropIndex('bs_bookings_meta_bid_key'));
        Schema::table('bs_events_meta', fn (Blueprint $t) => $t->dropIndex('bs_events_meta_eid_key'));
    }
};
