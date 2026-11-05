<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Support;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\TournamentBase\Support\CourtNames;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Turns a match row (TournamentManager::matches()) into the props of <x-tournament-base::match-card>.
 *
 * The action follows the write rule of ZF2's MatchController: admins and the two players may write, and
 * in a test tournament the simulated player may write his own matches although the test mode is
 * otherwise read-only.
 */
final class MatchPresenter
{
    private const BADGE = [
        'played' => ['good', 'tournament::common.match.played'],
        'scheduled' => ['neutral', 'tournament::common.match.scheduled'],
        'open' => ['attention', 'tournament::common.match.open'],
    ];

    public function __construct(private readonly TournamentManager $m) {}

    /**
     * @param  array<string, mixed>  $match
     * @param  array<int, array<string, mixed>>  $players  tpid => row
     * @return array{context: string, badge: array{group: string, label: string}, players: list<array{name: string, winner: bool}>, score: list<string>, meta: string|null, action: array{label: string, variant: string, url: string}|null}
     */
    public function present(array $match, array $players, ?AccessContext $access, Tournament $t): array
    {
        $state = $this->m->matchState($match);
        [$group, $labelKey] = self::BADGE[$state];
        $label = __($labelKey);
        if ($state === 'played' && $match['confirmed_uid']) {
            $label = __('tournament::common.match.played_confirmed');
        }

        $winner = (int) $match['winner'];
        $out = [];
        foreach (['a', 'b'] as $side) {
            $tpid = $match['tpid_'.$side] !== null ? (int) $match['tpid_'.$side] : null;
            $out[] = [
                'name' => $tpid !== null && isset($players[$tpid]) ? (string) $players[$tpid]['label'] : __('tournament::common.match.open_player'),
                'winner' => $tpid !== null && $winner === $tpid,
            ];
        }

        return [
            'context' => $this->context($match, $t),
            'badge' => ['group' => $group, 'label' => $label],
            'players' => $out,
            'score' => $this->score($match),
            'meta' => $this->meta($match),
            'action' => $this->action($match, $state, $access, $t),
        ];
    }

    /**
     * @param  array<string, mixed>  $match
     * @return 'played'|'scheduled'|'open'
     */
    public function state(array $match): string
    {
        return $this->m->matchState($match);
    }

    /** @param  array<string, mixed>  $match */
    private function context(array $match, Tournament $t): string
    {
        if ($match['phase'] === 'group') {
            return __('tournament::common.match.context_group', ['group' => $match['grp'], 'slot' => (int) $match['slot']]);
        }

        $phase = (string) $match['phase'];
        try {
            $phase = (string) ($this->m->format((int) $t->tid)->phase($phase)['label'] ?? $phase);
        } catch (Throwable) {
            // unknown phase or no format: show the key, as ZF2 did
        }

        return $match['rnd'] ? __('tournament::common.match.context_round', ['phase' => $phase, 'round' => (int) $match['rnd']]) : $phase;
    }

    /**
     * Games per set and player ("6 3 10" / "4 6 8"); a walkover shows "w.o." for the winner, a retirement the
     * games played plus "w.o." on the winner's line.
     *
     * @param  array<string, mixed>  $match
     * @return list<string>
     */
    public function score(array $match): array
    {
        if (in_array($match['retired'], ['wo_a', 'wo_b'], true)) {
            return $match['retired'] === 'wo_b' ? ['w.o.', ''] : ['', 'w.o.'];
        }

        $line = static function (array $keys) use ($match): string {
            $games = [];
            foreach ($keys as $key) {
                if ($match[$key] !== null && $match[$key] !== '') {
                    $games[] = (string) (int) $match[$key];
                }
            }

            return implode(' ', $games);
        };

        $score = [$line(['s1a', 's2a', 'mtba']), $line(['s1b', 's2b', 'mtbb'])];

        // A retirement keeps the games played and is marked "w.o." as in ZF2 (the retiring side loses).
        if (in_array($match['retired'], ['ret_a', 'ret_b'], true)) {
            $winnerSide = $match['retired'] === 'ret_a' ? 1 : 0;
            $score[$winnerSide] = trim($score[$winnerSide].' w.o.');
        }

        return $score;
    }

    /**
     * Date, time and court of a match ("Mo, 3. Mai · 17:00 · Court D"), or null without a date.
     *
     * @param  array<string, mixed>  $match
     */
    public function meta(array $match): ?string
    {
        if (! $match['played']) {
            return null;
        }

        $date = CarbonImmutable::parse((string) $match['played']);
        $parts = [__('tournament::common.match.date', [
            'weekday' => __('tournament::common.weekdays_short')[$date->dayOfWeek],
            'day' => $date->day,
            'month' => __('tournament::common.months_short')[$date->month - 1],
        ])];

        if ($match['played_time']) {
            $parts[] = substr((string) $match['played_time'], 0, 5);
        }

        if ($match['court'] !== null && $match['court'] !== '') {
            // The alias as in the old schedule; without a configured alias the plain "Platz n".
            $alias = CourtNames::short($match['court']);
            $parts[] = $alias !== (string) $match['court'] ? $alias : CourtNames::label($match['court']);
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $match
     * @return array{label: string, variant: string, url: string}|null
     */
    private function action(array $match, string $state, ?AccessContext $access, Tournament $t): ?array
    {
        if ($access === null || $state === 'played') {
            return null;
        }

        $isOwn = $access->tpid !== null
            && ((int) $match['tpid_a'] === $access->tpid || (int) $match['tpid_b'] === $access->tpid);
        $isTest = ! empty($t->is_test);
        $canWrite = ($access->isAdmin() || $isOwn)
            && ($access->canWrite || ($isTest && $access->simulation !== null));

        if (! $canWrite) {
            return null;
        }

        $url = route('tournament.match', ['tmid' => (int) $match['tmid']]);

        return $state === 'open'
            ? ['label' => __('tournament::common.match.enter_date'), 'variant' => 'primary', 'url' => $url]
            : ['label' => __('tournament::common.match.enter_result'), 'variant' => 'secondary', 'url' => $url];
    }
}
