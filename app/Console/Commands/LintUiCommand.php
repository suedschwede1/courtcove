<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\UiLinter;
use App\Services\UiRules;
use Illuminate\Console\Command;

class LintUiCommand extends Command
{
    protected $signature = 'lint:ui
        {--summary : Only the summary, no individual warnings}
        {--rule=   : Only rules of one category (e.g. BTN, INP, CLR)}
        {--fail-over= : Exit with code 1 when there are more than N warnings}';

    protected $description = 'Check views and CSS for design system violations';

    public function handle(): int
    {
        $rules = UiRules::all();

        if ($filterRule = $this->option('rule')) {
            $rules = array_values(array_filter(
                $rules,
                fn ($r) => str_starts_with($r['id'], strtoupper((string) $filterRule))
            ));
        }

        $roots = [
            resource_path('views'),
            resource_path('css'),
            ...(glob(app_path('Modules/*/resources/views'), GLOB_ONLYDIR) ?: []),
        ];

        $linter = new UiLinter($roots, $rules, base_path());
        $findings = $linter->run();
        $count = count($findings);

        if (! $this->option('summary')) {
            foreach ($findings as $f) {
                $this->line(sprintf('<comment>[WARN] %s</comment>  %s:%d', $f['rule'], $f['file'], $f['line']));
                $this->line('       '.$f['message']);
                $this->line('');
            }
        }

        $this->line(str_repeat('-', 55));

        $fileCount = count(array_unique(array_column($findings, 'file')));
        $this->warn("{$count} warnings in {$fileCount} files.");

        $failOver = $this->option('fail-over');
        if ($failOver !== null && $failOver !== '' && $count > (int) $failOver) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
