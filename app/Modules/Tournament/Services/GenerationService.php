<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Tournament\Models\TournamentLine;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Support\ServiceMessages;
use Illuminate\Support\Facades\DB;

/**
 * Transactional, idempotent persistence of everything derived from the format (port of the ZF2
 * GenerationService): group heads, group matches, bracket lines and bracket matches.
 *
 * Deliberate deviation: the manager cache is dropped at the start and after the heads update. The old
 * shared manager served player rows read before a token pick, so the matches of the group that pick
 * completed only appeared on the next generation.
 */
final class GenerationService
{
    public function __construct(
        private readonly TournamentManager $m,
        private readonly GeneratorService $g,
    ) {}

    /** @return array{created: array{heads: int, matches: int, lines: int, bracketMatches: int}, skipped: list<string>} */
    public function generateCurrent(): array
    {
        return $this->generate((int) $this->m->current()->tid);
    }

    /** @return array{created: array{heads: int, matches: int, lines: int, bracketMatches: int}, skipped: list<string>} */
    public function generate(int $tid): array
    {
        $this->m->forget($tid);

        try {
            return DB::transaction(function () use ($tid): array {
                $format = $this->m->format($tid);
                $format->validate();

                return $this->persist($tid, $format);
            });
        } finally {
            // Rows read inside a rolled-back (or committed) transaction must not outlive it.
            $this->m->forget($tid);
        }
    }

    /** @return array{created: array{heads: int, matches: int, lines: int, bracketMatches: int}, skipped: list<string>} */
    private function persist(int $tid, FormatService $format): array
    {
        $created = ['heads' => 0, 'matches' => 0, 'lines' => 0, 'bracketMatches' => 0];
        $skipped = [];
        $allPlayers = array_values($this->m->players($tid));

        foreach ($format->groupPhases() as $phase) {
            foreach ($this->g->assignHeads($format, $phase['key'], $allPlayers) as $tpid => $assignment) {
                $current = $this->m->player($tid, (int) $tpid);
                if ($current && $current['grp'] === $assignment['grp']
                    && (int) $current['grp_pos'] === (int) $assignment['grp_pos']) {
                    continue;
                }
                TournamentPlayer::query()->where('tid', $tid)->where('tpid', (int) $tpid)->update($assignment);
                $created['heads']++;
            }
        }

        $this->m->forget($tid);

        $existing = [];
        foreach ($this->m->matches($tid) as $match) {
            if ($match['mkey']) {
                $existing[] = $match['mkey'];
            }
        }

        foreach ($format->groupPhases() as $phase) {
            $byGroup = [];
            foreach ($phase['groups'] as $group) {
                $byGroup[$group['key']] = $this->m->groupPlayers($tid, $group['key']);
            }
            $result = $this->g->groupMatches($format, $phase['key'], $byGroup, $existing);
            foreach ($result['matches'] as $match) {
                TournamentMatch::query()->insert([
                    'tid' => $tid, 'phase' => $match['phase'], 'mkey' => $match['mkey'],
                    'grp' => $match['grp'], 'rnd' => $match['rnd'], 'slot' => $match['slot'],
                    'tpid_a' => $match['tpid_a'], 'tpid_b' => $match['tpid_b'],
                ]);
                $created['matches']++;
            }
            foreach ($result['skipped'] as $groupKey => $reason) {
                $skipped[] = __('tournament::admin.generate.skipped', ['group' => $groupKey, 'reason' => ServiceMessages::skippedReason((string) $reason)]);
            }
        }

        foreach ($format->bracketPhases() as $phase) {
            foreach ($this->g->bracketLines($format, $phase['key']) as $line) {
                $found = TournamentLine::query()
                    ->where('tid', $tid)->where('phase', $phase['key'])->where('line', $line['line'])
                    ->exists();
                if ($found) {
                    continue;
                }
                TournamentLine::query()->insert([
                    'tid' => $tid, 'phase' => $phase['key'], 'line' => $line['line'],
                    'kind' => $line['kind'], 'seed_no' => $line['seed_no'], 'pool' => $line['pool'],
                ]);
                $created['lines']++;
            }
            foreach ($this->g->bracketMatches($format, $phase['key'], $existing) as $match) {
                TournamentMatch::query()->insert([
                    'tid' => $tid, 'phase' => $match['phase'], 'mkey' => $match['mkey'],
                    'grp' => null, 'rnd' => $match['rnd'], 'slot' => $match['slot'],
                ]);
                $created['bracketMatches']++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
