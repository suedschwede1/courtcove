<?php

declare(strict_types=1);

namespace App\Modules\Draw\Console;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\DrawVerifier;
use Illuminate\Console\Command;

class DrawVerifyCommand extends Command
{
    protected $signature = 'draw:verify
        {slug? : Verify one draw}
        {--all : Verify every draw}
        {--compare-dir= : Directory of record JSON captured from ZF2 (<slug>.json)}';

    protected $description = 'Recomputes commitment, seed and assignment of stored draws from the database (never prints seeds).';

    public function handle(DrawVerifier $verifier): int
    {
        $slug = $this->argument('slug');

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
}
