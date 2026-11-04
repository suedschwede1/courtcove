<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marks the `create_bs_*_table` migrations as applied on a database that was
 * created by the legacy ZF2 system, so `migrate` does not try to recreate the
 * existing tables. The only write this command ever performs is inserting rows
 * into the migrations table; it never creates, alters, drops or truncates tables.
 */
class BaselineLegacySchemaCommand extends Command
{
    protected $signature = 'db:baseline-legacy
        {--dry-run : List the migrations that would be recorded without writing}';

    protected $description = 'Record create_bs_* migrations as applied for legacy tables that already exist';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $table = (string) config('database.migrations.table', 'migrations');

        if (! Schema::hasTable($table)) {
            $this->error("The migrations table '{$table}' does not exist. Run `php artisan migrate:install` first.");

            return self::FAILURE;
        }

        $applied = DB::table($table)->pluck('migration')->all();
        $batch = (int) (DB::table($table)->max('batch') ?? 0) ?: 1;

        $recorded = 0;

        foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
            $name = basename($file, '.php');

            if (! preg_match('/create_(bs_[a-z_]+)_table/', $name, $m)) {
                continue;
            }

            if (in_array($name, $applied, true) || ! Schema::hasTable($m[1])) {
                continue;
            }

            if (! $dryRun) {
                DB::table($table)->insert(['migration' => $name, 'batch' => $batch]);
            }

            $this->line(($dryRun ? 'Would record ' : 'Recorded ').$name." (table {$m[1]}, batch {$batch})");
            $recorded++;
        }

        $this->info(($dryRun ? 'Would record ' : 'Recorded ').$recorded.' migration(s).');

        return self::SUCCESS;
    }
}
