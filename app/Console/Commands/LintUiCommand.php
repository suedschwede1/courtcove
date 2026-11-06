<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\UiLinter;
use App\Services\UiRules;
use Illuminate\Console\Command;
use JsonException;

class LintUiCommand extends Command
{
    protected $signature = 'lint:ui
        {--summary : Only the summary, no individual warnings}
        {--rule=   : Only rules of one category (e.g. BTN, INP, CLR)}
        {--fail-over= : Exit with code 1 when there are more than N warnings in total}
        {--strict : Report baselined legacy findings as errors too}
        {--write-baseline : Create the initial legacy inventory; never overwrites it}
        {--prune-baseline : Remove resolved exceptions without accepting new ones}';

    protected $description = 'Check views and CSS for design system violations (docs/DESIGN.md)';

    public function handle(): int
    {
        if ($this->option('write-baseline') && $this->option('prune-baseline')) {
            $this->error('Choose one baseline operation.');

            return self::FAILURE;
        }

        $rules = UiRules::all();
        if ($filterRule = $this->option('rule')) {
            $rules = array_values(array_filter(
                $rules,
                fn ($r) => str_starts_with($r['id'], strtoupper((string) $filterRule))
            ));
            if ($rules === [] || $this->option('write-baseline') || $this->option('prune-baseline')) {
                $this->error('Unknown rule, or a baseline operation combined with a rule filter.');

                return self::FAILURE;
            }
        }

        $roots = config('ui-design.roots');
        if (! is_array($roots)) {
            $this->error('Missing UI configuration (config/ui-design.php). Run php artisan config:clear first.');

            return self::FAILURE;
        }

        $linter = new UiLinter(array_values($roots), $rules, base_path());
        if ($linter->files() === []) {
            $this->error('No UI sources found; check ui-design.roots.');

            return self::FAILURE;
        }

        $findings = $linter->run();
        $path = (string) config('ui-design.baseline');
        $current = [];
        foreach ($findings as $finding) {
            $key = $finding['fingerprint'];
            $current[$key] ??= ['file' => $finding['file'], 'rule' => $finding['rule'], 'source' => $finding['source'], 'count' => 0];
            $current[$key]['count']++;
        }

        if ($this->option('write-baseline')) {
            if (file_exists($path) || array_filter($findings, fn (array $f): bool => $this->migrated($f['file'])) !== []) {
                $this->error('Refusing to overwrite a baseline or accept violations in migrated paths.');

                return self::FAILURE;
            }
            $this->writeBaseline($path, $current);
            $this->info('Initial legacy inventory created. Review it before committing.');

            return self::SUCCESS;
        }

        try {
            $baseline = $this->readBaseline($path);
        } catch (JsonException $e) {
            $this->error('Cannot read UI baseline: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('prune-baseline')) {
            $pruned = [];
            foreach ($baseline as $key => $entry) {
                if (isset($current[$key]) && ! $this->migrated($current[$key]['file'])) {
                    $pruned[$key] = $current[$key];
                    $pruned[$key]['count'] = min($entry['count'], $current[$key]['count']);
                }
            }
            $this->writeBaseline($path, $pruned);
            $baseline = $pruned;
        }

        $seen = [];
        $blocking = 0;
        foreach ($findings as $f) {
            $key = $f['fingerprint'];
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            if (! $this->option('strict') && ! $this->migrated($f['file']) && $seen[$key] <= ($baseline[$key]['count'] ?? 0)) {
                continue;
            }
            $blocking++;
            if (! $this->option('summary')) {
                $this->line(sprintf('<comment>[WARN] %s</comment>  %s:%d', $f['rule'], $f['file'], $f['line']));
                $this->line('       '.$f['message']);
                $this->line('');
            }
        }

        $obsolete = $this->obsoleteExceptions($baseline, $current, array_column($rules, 'id'));

        $count = count($findings);
        $fileCount = count(array_unique(array_column($findings, 'file')));
        $this->line(str_repeat('-', 55));
        $this->warn("{$count} warnings in {$fileCount} files.");
        $this->line(sprintf('%d blocking findings; %d baselined legacy findings.', $blocking, $count - $blocking));

        if ($obsolete !== []) {
            // A stale allowance would let the fixed defect come back unnoticed.
            $this->error(sprintf('Obsolete baseline exceptions (the findings are gone): %d', count($obsolete)));
            foreach ($obsolete as $entry) {
                $this->line(sprintf('  [%s] %s (%d unused): %s', $entry['rule'], $entry['file'], $entry['unused'], $entry['source']));
            }
            $this->line('Remove them with: php artisan lint:ui --prune-baseline');
        }

        $failOver = $this->option('fail-over');
        if ($failOver !== null && $failOver !== '' && $count > (int) $failOver) {
            return self::FAILURE;
        }

        return $blocking === 0 && $obsolete === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Baseline allowances no current finding uses: the entry's finding is gone, it
     * occurs fewer times than allowed, or its path has been migrated since. Only
     * entries of the rules that ran are judged, so --rule stays meaningful.
     *
     * @param  array<string, array{file: string, rule: string, source: string, count: int}>  $baseline
     * @param  array<string, array{file: string, rule: string, source: string, count: int}>  $current
     * @param  list<string>  $ruleIds
     * @return list<array{file: string, rule: string, source: string, unused: int}>
     */
    private function obsoleteExceptions(array $baseline, array $current, array $ruleIds): array
    {
        $obsolete = [];
        foreach ($baseline as $key => $entry) {
            if (! in_array($entry['rule'], $ruleIds, true)) {
                continue;
            }
            $usable = $this->migrated($entry['file']) ? 0 : ($current[$key]['count'] ?? 0);
            $unused = $entry['count'] - min($entry['count'], $usable);
            if ($unused > 0) {
                $obsolete[] = ['file' => $entry['file'], 'rule' => $entry['rule'], 'source' => $entry['source'], 'unused' => $unused];
            }
        }

        return $obsolete;
    }

    /**
     * @return array<string, array{file: string, rule: string, source: string, count: int}>
     *
     * @throws JsonException
     */
    private function readBaseline(string $path): array
    {
        if (! is_file($path)) {
            throw new JsonException('Missing baseline. Create and review the initial inventory.');
        }
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data) || ($data['version'] ?? null) !== 1 || ! is_array($data['findings'] ?? null)) {
            throw new JsonException('Invalid baseline schema.');
        }
        foreach ($data['findings'] as $entry) {
            if (! is_array($entry) || ! is_int($entry['count'] ?? null) || $entry['count'] < 1) {
                throw new JsonException('Invalid baseline count.');
            }
            foreach (['file', 'rule', 'source'] as $field) {
                if (! is_string($entry[$field] ?? null)) {
                    throw new JsonException('Invalid baseline entry: missing '.$field.'.');
                }
            }
        }

        return $data['findings'];
    }

    private function migrated(string $path): bool
    {
        foreach ((array) config('ui-design.migrated', []) as $prefix) {
            if (str_starts_with($path, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, array<string, mixed>> $findings */
    private function writeBaseline(string $path, array $findings): void
    {
        ksort($findings);
        file_put_contents($path, json_encode(
            ['version' => 1, 'findings' => (object) $findings],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        )."\n");
    }
}
