<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Console;

use App\Modules\TournamentBase\Staging\ModuleScrub;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Destructive: empties the tables of every tagged module and reseeds fictional demo data.
 * APP_ENV is deliberately not checked (staging runs with the production environment);
 * the guard is the database name plus an explicit --force.
 */
class Phase2ScrubStagingCommand extends Command
{
    protected $signature = 'phase2:scrub-staging {--force : Required, confirms the target is a staging database}';

    protected $description = 'STAGING ONLY: replace the phase 2 module data with fictional demo data.';

    public function handle(): int
    {
        $db = (string) DB::connection()->getDatabaseName();

        if (! str_ends_with($db, '_staging')) {
            $this->error("Refusing: database '{$db}' does not end in '_staging'.");

            return 1;
        }

        if ($db === config('tournament-base.live_database')) {
            $this->error("Refusing: database '{$db}' is the live database.");

            return 1;
        }

        if (! $this->option('force')) {
            $this->error('Refusing: pass --force.');

            return 1;
        }

        /** @var list<ModuleScrub> $scrubs */
        $scrubs = iterator_to_array($this->laravel->tagged('phase2.scrub'), false);

        $tables = array_merge(...array_map(fn (ModuleScrub $scrub): array => $scrub->tables(), $scrubs) ?: [[]]);

        $this->line("Database: {$db}");
        $this->line('Tables: '.implode(', ', $tables));

        if ($this->input->isInteractive() && ! $this->confirm('Truncate these tables and reseed?', false)) {
            $this->line('Aborted.');

            return 1;
        }

        foreach ($scrubs as $scrub) {
            foreach ($scrub->tables() as $table) {
                DB::table($table)->truncate();
            }

            $this->call('db:seed', ['--class' => $scrub->seeder(), '--force' => true]);
            $this->line("Scrubbed {$scrub->module()}: ".implode(', ', $scrub->tables()));
        }

        return 0;
    }
}
