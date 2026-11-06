<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Models\User;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\TournamentBase\Services\InvalidScoreException;
use App\Modules\TournamentBase\Services\ScoreService;
use App\Modules\TournamentBase\Support\ActionRefused;

/**
 * Date, result, confirmation and deletion of a match (port of ZF2's MatchController save/confirm/delete).
 *
 * The caller has checked the write permission (MatchWriteRule) for a match resolved within the viewer's
 * tournament. Refusals throw ActionRefused (or InvalidScoreException with ScoreService's own text) and write
 * nothing. As in ZF2 each action is ONE plain update of the match row, without a transaction or row lock.
 *
 * The winner is never entered, it follows from the sets. A changed result resets the opponent's
 * confirmation, otherwise the confirmation would be worthless.
 */
final class MatchResultService
{
    private const RESULT_COLUMNS = [
        's1a', 's1b', 's2a', 's2b', 'mtba', 'mtbb', 'sets_a', 'sets_b', 'games_a', 'games_b',
        'score', 'winner', 'entered_uid', 'entered', 'confirmed_uid', 'confirmed',
    ];

    public function __construct(
        private readonly ScoreService $scores,
        private readonly TournamentManager $m,
    ) {}

    /**
     * Saves the date and/or the result.
     *
     * @param  array{s1a?: string|null, s1b?: string|null, s2a?: string|null, s2b?: string|null, mtba?: string|null, mtbb?: string|null, wo?: string|null, date?: string, time?: string, court?: string|null}  $input  SaveMatchRequest::matchInput()
     * @return string translation key of the success message (replace `score` with the stored score)
     *
     * @throws ActionRefused|InvalidScoreException
     */
    public function save(TournamentMatch $match, array $input, User $by, bool $isAdmin): string
    {
        $this->assertNotLocked($match, $isAdmin);

        $score = [];
        foreach (['s1a', 's1b', 's2a', 's2b', 'mtba', 'mtbb'] as $field) {
            $score[$field] = $input[$field] ?? null;
        }

        $wo = in_array($input['wo'] ?? null, ['a', 'b'], true) ? $input['wo'] : null;
        $date = trim((string) ($input['date'] ?? ''));
        $time = trim((string) ($input['time'] ?? ''));
        $court = in_array($input['court'] ?? null, ['1', '2', '3'], true) ? $input['court'] : null;

        $data = [
            'played' => $date !== '' ? $this->date($date) : null,
            'played_time' => $time !== '' ? $this->time($time) : null,
            'court' => $court,
        ];

        $hasResult = $wo !== null;
        foreach ($score as $value) {
            if ($value !== null && $value !== '') {
                $hasResult = true;
            }
        }

        if ($hasResult && ($match->tpid_a === null || $match->tpid_b === null)) {
            throw new ActionRefused('tournament::match.flash.players_missing');
        }

        if ($wo !== null) {
            // Not played (w.o.): counted like a clean 6:0 6:0 for the opponent; only `retired` tells it apart.
            $aAbsent = $wo === 'a';
            $this->write($match, $data + [
                's1a' => $aAbsent ? 0 : 6, 's1b' => $aAbsent ? 6 : 0,
                's2a' => $aAbsent ? 0 : 6, 's2b' => $aAbsent ? 6 : 0,
                'mtba' => null, 'mtbb' => null,
                'sets_a' => $aAbsent ? 0 : 2, 'sets_b' => $aAbsent ? 2 : 0,
                'games_a' => $aAbsent ? 0 : 12, 'games_b' => $aAbsent ? 12 : 0,
                'score' => '6:0 6:0',
                'winner' => $aAbsent ? (int) $match->tpid_b : (int) $match->tpid_a,
                'retired' => $aAbsent ? 'wo_a' : 'wo_b',
                'entered_uid' => (int) $by->uid,
                'entered' => $this->now(),
                'confirmed_uid' => null,
                'confirmed' => null,
            ]);

            return 'tournament::match.flash.walkover_saved';
        }

        if ($hasResult) {
            $result = $this->scores->evaluate($score);

            $this->write($match, $data + [
                's1a' => $result['s1a'], 's1b' => $result['s1b'],
                's2a' => $result['s2a'], 's2b' => $result['s2b'],
                'mtba' => $result['mtba'], 'mtbb' => $result['mtbb'],
                'sets_a' => $result['sets_a'], 'sets_b' => $result['sets_b'],
                'games_a' => $result['games_a'], 'games_b' => $result['games_b'],
                'score' => $result['score'],
                'winner' => $result['winner_side'] === 'a' ? (int) $match->tpid_a : (int) $match->tpid_b,
                'retired' => 'none',
                'entered_uid' => (int) $by->uid,
                'entered' => $this->now(),
                // a changed result needs a new confirmation
                'confirmed_uid' => null,
                'confirmed' => null,
            ]);

            return 'tournament::match.flash.result_saved';
        }

        if ($data['played'] === null || $data['played_time'] === null) {
            throw new ActionRefused('tournament::match.flash.schedule_incomplete');
        }

        $this->write($match, $data);

        return 'tournament::match.flash.schedule_saved';
    }

    /**
     * Confirmation by the opponent. Whoever entered the result cannot confirm it (an empty click otherwise);
     * an admin can, he overrides anyway.
     *
     * @throws ActionRefused
     */
    public function confirm(TournamentMatch $match, User $by, bool $isAdmin): void
    {
        if (! $match->winner) {
            throw new ActionRefused('tournament::match.flash.nothing_to_confirm');
        }

        if (! $isAdmin && (int) $match->entered_uid === (int) $by->uid) {
            throw new ActionRefused('tournament::match.flash.confirm_by_opponent');
        }

        $this->write($match, [
            'confirmed_uid' => (int) $by->uid,
            'confirmed' => $this->now(),
        ]);
    }

    /**
     * Deletes the result; date, time and court stay.
     *
     * @throws ActionRefused
     */
    public function delete(TournamentMatch $match, bool $isAdmin): void
    {
        $this->assertNotLocked($match, $isAdmin);

        $data = array_fill_keys(self::RESULT_COLUMNS, null);
        // Deviation from ZF2 (which left `retired` behind): a deleted walkover must not keep its w.o. mark,
        // otherwise the cards show "w.o." on an unplayed match and the form preselects the walkover again.
        $data['retired'] = 'none';

        $this->write($match, $data);
    }

    /** A confirmed result is fixed for participants; the admin may still correct it (and resets the confirmation). */
    private function assertNotLocked(TournamentMatch $match, bool $isAdmin): void
    {
        if ($match->confirmed_uid && ! $isAdmin) {
            throw new ActionRefused('tournament::match.flash.locked');
        }
    }

    /** @param  array<string, mixed>  $data */
    private function write(TournamentMatch $match, array $data): void
    {
        TournamentMatch::query()->where('tid', $match->tid)->where('tmid', $match->tmid)->update($data);
        $match->forceFill($data)->syncOriginal();
        $this->m->forget((int) $match->tid);
    }

    /** A real calendar date `Y-m-d` within the DATE column range. */
    private function date(string $value): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)
            || (int) $m[1] < 1000 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new ActionRefused('tournament::match.flash.invalid_date');
        }

        return $value;
    }

    /** `H:i` (the time input) or `H:i:s`, stored as `H:i:s`. */
    private function time(string $value): string
    {
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $m)) {
            throw new ActionRefused('tournament::match.flash.invalid_time');
        }

        return sprintf('%s:%s:%s', $m[1], $m[2], $m[3] ?? '00');
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }
}
