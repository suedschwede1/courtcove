<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_users_meta', function (Blueprint $table) {
            $table->index(['uid', 'key'], 'bs_users_meta_uid_key');
        });

        Schema::table('bs_squares_meta', function (Blueprint $table) {
            $table->index(['sid', 'key'], 'bs_squares_meta_sid_key');
        });

        Schema::table('bs_bookings_meta', function (Blueprint $table) {
            $table->index(['bid', 'key'], 'bs_bookings_meta_bid_key');
        });

        Schema::table('bs_events_meta', function (Blueprint $table) {
            $table->index(['eid', 'key'], 'bs_events_meta_eid_key');
        });
    }

    public function down(): void
    {
        Schema::table('bs_users_meta', fn (Blueprint $t) => $t->dropIndex('bs_users_meta_uid_key'));
        Schema::table('bs_squares_meta', fn (Blueprint $t) => $t->dropIndex('bs_squares_meta_sid_key'));
        Schema::table('bs_bookings_meta', fn (Blueprint $t) => $t->dropIndex('bs_bookings_meta_bid_key'));
        Schema::table('bs_events_meta', fn (Blueprint $t) => $t->dropIndex('bs_events_meta_eid_key'));
    }
};
