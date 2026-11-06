<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The limit of open bookings per user used to be set per court although it counted over all
 * courts. It is now one global option; this seeds it once from the court values (the smallest
 * positive one, so no rule gets looser). Existing option values are never overwritten, the
 * court column stays untouched and is no longer read.
 */
return new class extends Migration
{
    private const KEY = 'booking.max_active_bookings';

    public function up(): void
    {
        if (! Schema::hasTable('bs_options') || ! Schema::hasTable('bs_squares') || ! Schema::hasColumn('bs_squares', 'max_active_bookings')) {
            return;
        }

        if (DB::table('bs_options')->where('key', self::KEY)->exists()) {
            return;
        }

        $smallest = DB::table('bs_squares')->where('max_active_bookings', '>', 0)->min('max_active_bookings');

        DB::table('bs_options')->insert([
            'key' => self::KEY,
            'value' => (string) ($smallest ?? 0),
            'locale' => null,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('bs_options')) {
            DB::table('bs_options')->where('key', self::KEY)->delete();
        }
    }
};
