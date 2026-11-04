<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Entry points to the tournament modules that still run on the legacy ZF2 system.
 *
 * Mirrors ZF2's TournamentManager::current() + TournamentLink view helper. The
 * bs_tournaments table is owned by ZF2 (no Laravel migration), so every lookup is
 * best-effort: the booking calendar must never fail because of it.
 */
class TournamentLinks
{
    /**
     * @return array{title: string, url: string}|null
     */
    public function currentChampionship(): ?array
    {
        try {
            $tournament = DB::table('bs_tournaments')
                ->select(['title', 'status'])
                ->where('is_test', 0)
                ->orderByDesc('active')
                ->orderByDesc('season')
                ->orderByDesc('tid')
                ->first();
        } catch (\Throwable) {
            return null;
        }

        if ($tournament === null || $tournament->status === 'finished') {
            return null;
        }

        return [
            'title' => (string) $tournament->title,
            'url' => $this->absolute('/tournament'),
        ];
    }

    public function pyramidUrl(): string
    {
        return $this->absolute('/pyramide');
    }

    public function mixedUrl(): string
    {
        return $this->absolute('/mixed-tournament');
    }

    /**
     * Same host (empty base_url): url() honors the /booking base path. Separate host: prefix it.
     */
    private function absolute(string $path): string
    {
        $base = rtrim((string) config('services.tournament.base_url'), '/');

        return $base === '' ? url($path) : $base.$path;
    }
}
