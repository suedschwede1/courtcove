<?php

declare(strict_types=1);

namespace App\Modules\Draw\Console;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\DrawVerifier;
use App\Modules\Draw\Support\DrawRecordPresenter;
use Illuminate\Console\Command;

class DrawVerifyCommand extends Command
{
    protected $signature = 'draw:verify
        {slug? : Verify one draw}
        {--all : Verify every draw}
        {--compare-dir= : Directory of record JSON captured from ZF2 (<slug>.json)}
        {--revealed-slugs : Print only the slugs of revealed draws, one per line}';

    protected $description = 'Recomputes commitment, seed and assignment of stored draws from the database (never prints seeds).';

    public function handle(DrawVerifier $verifier): int
    {
        $slug = $this->argument('slug');

        if ($this->option('revealed-slugs')) {
            return $this->revealedSlugs();
        }

        if ($this->option('compare-dir') !== null) {
            return $this->compareDir((string) $this->option('compare-dir'));
        }

        if ($slug === null && ! $this->option('all')) {
            $this->error('Pass a slug or --all.');

            return 2;
        }

        if ($slug !== null) {
            $draw = Draw::slug((string) $slug)->first();

            if ($draw === null) {
                $this->error("Draw {$slug} not found.");

                return 1;
            }

            $draws = collect([$draw]);
        } else {
            $draws = Draw::query()->orderBy('did')->get();
        }

        $failed = false;

        foreach ($draws as $draw) {
            $result = $verifier->verify($draw);
            $head = "{$result->slug} [{$result->status}/{$result->mode}]";

            if (! $result->ok()) {
                $failed = true;
                $this->line($head.' FAIL: '.implode(', ', $result->failures()));
            } elseif ($result->executed()) {
                $this->line($head.' OK');
            } else {
                $this->line($head.' not executed (canonical OK)');
            }
        }

        return $failed ? 1 : 0;
    }

    private function revealedSlugs(): int
    {
        foreach (Draw::query()->orderBy('did')->get() as $draw) {
            if ($draw->isRevealed()) {
                $this->line((string) $draw->slug);
            }
        }

        return 0;
    }

    private function compareDir(string $dir): int
    {
        if (! is_dir($dir)) {
            $this->error("Directory {$dir} not found.");

            return 2;
        }

        $files = glob(rtrim($dir, '/\\').'/*.json') ?: [];
        sort($files);
        $failed = false;

        foreach ($files as $file) {
            $slug = basename($file, '.json');
            $draw = Draw::slug($slug)->first();

            if ($draw === null) {
                $failed = true;
                $this->line("{$slug} compare: missing in database");

                continue;
            }

            $captured = json_decode((string) file_get_contents($file), true);
            $ours = json_decode(json_encode(DrawRecordPresenter::json($draw)), true);

            if ($this->normalize($captured) === $this->normalize($ours)) {
                $this->line("{$slug} compare: identical");

                continue;
            }

            $failed = true;
            $keys = [];

            if (is_array($captured) && is_array($ours)) {
                foreach (array_unique([...array_keys($captured), ...array_keys($ours)]) as $key) {
                    if (! array_key_exists($key, $captured) || ! array_key_exists($key, $ours)
                        || $this->normalize($captured[$key]) !== $this->normalize($ours[$key])) {
                        $keys[] = (string) $key;
                    }
                }
            }

            $this->line("{$slug} compare: differs in ".($keys === [] ? '(not an object)' : implode(', ', $keys)));
        }

        return $failed ? 1 : 0;
    }

    /** Sorts object keys recursively; lists keep their order. */
    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(fn (mixed $v): mixed => $this->normalize($v), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
