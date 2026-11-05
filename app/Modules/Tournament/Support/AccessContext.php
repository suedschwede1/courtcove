<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Support;

/**
 * Access of one user to one tournament for one request (the array of ZF2's TournamentAccess plugin).
 *
 * `simulation` is set only in the admin test mode: {mode: 'member'|'player', label, tpid?, realLevel}.
 */
final readonly class AccessContext
{
    /**
     * @param  array<string, mixed>|null  $player  the own (or simulated) player row
     * @param  array{mode: string, label: string, tpid?: int, realLevel: int}|null  $simulation
     */
    public function __construct(
        public int $level,
        public ?array $player,
        public ?int $tpid,
        public ?array $simulation,
        public bool $canWrite,
        public ?int $selectedTid,
    ) {}

    public function isAdmin(): bool
    {
        return $this->level >= TournamentAccess::ADMIN;
    }
}
