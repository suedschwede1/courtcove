<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\UiLinter;
use App\Services\UiRules;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class LintUiCommand extends Command
{
    protected $signature = 'lint:ui
        {--summary : Nur Zusammenfassung, keine Einzel-Warnungen}
        {--rule=   : Nur Regeln einer Kategorie (z.B. BTN, INP, CLR)}';

    protected $description = 'Blade-Templates auf Design-Guide-Verstöße prüfen';

    public function handle(): int
    {
        $viewsPath = resource_path('views');
        $rules     = UiRules::all();

        if ($filterRule = $this->option('rule')) {
            $rules = array_values(array_filter(
                $rules,
                fn($r) => str_starts_with($r['id'], strtoupper((string) $filterRule))
            ));
        }

        $linter   = new UiLinter($viewsPath, $rules);
        $findings = $linter->run();

        $fileCount = iterator_count(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath))
        );

        if (!$this->option('summary')) {
            foreach ($findings as $f) {
                $this->line(sprintf(
                    '<comment>[WARN] %s</comment>  %s:%d',
                    $f['rule'],
                    $f['file'],
                    $f['line'],
                ));
                $this->line('       ' . $f['message']);
                $this->line('');
            }
        }

        $this->line(str_repeat('─', 55));

        $count = count($findings);
        if ($count === 0) {
            $this->info("Keine Verstöße gefunden. {$fileCount} Dateien geprüft.");
        } else {
            $this->warn("{$count} " . ($count === 1 ? 'Warnung' : 'Warnungen') . " in {$fileCount} Dateien.");
        }

        return self::SUCCESS;
    }
}
