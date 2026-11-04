<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Console;

use App\Modules\TournamentBase\Preflight\ModulePreflight;
use App\Modules\TournamentBase\Preflight\PreflightResult;
use Illuminate\Console\Command;

class Phase2PreflightCommand extends Command
{
    protected $signature = 'phase2:preflight {module : draw|pyramid|mixed|tournament}';

    protected $description = 'Read-only checks of a phase 2 module against the live schema and data.';

    public function handle(): int
    {
        $module = (string) $this->argument('module');

        /** @var array<string, ModulePreflight> $registered */
        $registered = [];
        foreach ($this->laravel->tagged('phase2.preflight') as $preflight) {
            $registered[$preflight->module()] = $preflight;
        }

        if (! isset($registered[$module])) {
            $this->error("Unknown module {$module}. Registered: ".implode(', ', array_keys($registered)));

            return 2;
        }

        $counts = [PreflightResult::PASS => 0, PreflightResult::WARN => 0, PreflightResult::FAIL => 0, PreflightResult::SKIP => 0];

        foreach ($registered[$module]->run() as $result) {
            $counts[$result->status]++;
            $this->line($result->line());
        }

        $this->line("Preflight {$module}: {$counts['PASS']} PASS, {$counts['WARN']} WARN, {$counts['FAIL']} FAIL, {$counts['SKIP']} SKIP");

        return $counts[PreflightResult::FAIL] > 0 ? 1 : 0;
    }
}
