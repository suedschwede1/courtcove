<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services\Draws;

use App\Models\User;
use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Services\DrawCommitService;
use App\Modules\Draw\Services\DrawExecutor;
use App\Modules\Draw\Services\DrawService;
use App\Modules\Draw\Services\PayloadGuard;
use App\Modules\Draw\Services\RoundNotFutureException;
use App\Modules\Draw\Services\TokenService;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\TournamentBase\Support\ActionRefused;
use Exception;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use TypeError;

/**
 * Prepares the token draw of a group phase (ported from ZF2 AdminController::prepareDrawAction).
 *
 * Links the two modules: the tournament knows the pot and the slot list from the format, the Draw module
 * does commitment, tokens and the draw. The payload holds token names, not players - at commit time it is
 * not known who takes which token, and that is the point of the procedure.
 *
 * Draw round and pick deadline belong together but are not the same: the round is when the randomness
 * falls (the START of the picking), the deadline is the END. The lead time is the announcement window in
 * which the record link goes out while the randomness does not exist yet; at least 60 seconds, or the
 * result could be computed in advance. The deadline closes nothing automatically.
 *
 * A test tournament takes an already published round and executes at once (DrawExecutor, ZF2's forwarded
 * execute action), so the rehearsal runs without waiting.
 */
final class TokenDrawService
{
    /**
     * The token rule as committed by ZF2. It is part of the committed payload: byte-identical to the old
     * code (ASCII transliterations included), never translated.
     */
    public const RULE = 'Jeder Spieler im Lostopf waehlt einen Token. Die Slot-Liste wird mit '
        .'dem Zufall der drand-Runde %u gemischt; Token an Position i erhaelt '
        .'den Slot an Position i der gemischten Liste. Wer nicht waehlt, bekommt '
        .'beim Schliessen einen freien Token: freie Tokens werden nach Position '
        .'geordnet, Nichtwaehler nach Teilnehmernummer geordnet und anschliessend '
        .'mit dem finalen Seed reproduzierbar gemischt.';

    /** Draw title format of ZF2 (data, not UI). */
    public const TITLE = '%s - Auslosung %s';

    public function __construct(
        private readonly TournamentManager $m,
        private readonly TokenService $tokens,
        private readonly DrawService $draws,
        private readonly BeaconService $beacon,
        private readonly PayloadGuard $guard,
        private readonly DrawCommitService $commits,
        private readonly DrawExecutor $executor,
    ) {}

    /**
     * @return array{draw: Draw, flashes: list<array{type: string, text: string}>}
     *
     * @throws ActionRefused when nothing was created
     */
    public function prepare(Tournament $t, string $phaseKey, int $round, string $deadline, int $leadMinutes, int $inMinutes, User $by): array
    {
        $tid = (int) $t->tid;
        $isTest = ! empty($t->is_test);
        $now = now()->getTimestamp();
        $deadline = trim($deadline);
        $leadSeconds = max(60, $leadMinutes * 60);

        if ($inMinutes > 0 && $deadline === '') {
            $deadline = date('Y-m-d H:i:s', $now + $inMinutes * 60);
        }

        if ($deadline !== '') {
            $deadlineAt = strtotime($deadline);

            // strtotime also accepts relative values such as "+20000 years": a year outside DATETIME's
            // 1000-9999 would fail the insert in strict mode, so it is refused like an unparseable value.
            $year = $deadlineAt !== false ? (int) date('Y', $deadlineAt) : 0;

            if ($deadlineAt === false || $deadlineAt <= 0 || $year < 1000 || $year > 9999) {
                throw new ActionRefused('tournament::admin.prepare.invalid_deadline');
            }

            if ($round < 1) {
                // Draw as early as possible, but with the announcement window.
                try {
                    $round = $this->beacon->roundAt($now + $leadSeconds);
                    $drawAt = $this->beacon->roundTime($round);
                } catch (RuntimeException $e) {
                    throw new ActionRefused('tournament::admin.prepare.beacon_manual', ['message' => $e->getMessage()]);
                }

                if ($deadlineAt <= $drawAt + 300) {
                    throw new ActionRefused('tournament::admin.prepare.deadline_too_early', [
                        'draw_at' => date('d.m.Y H:i', $drawAt),
                        'round' => $round,
                        'lead' => (int) round($leadSeconds / 60),
                        'min' => date('d.m.Y H:i', $drawAt + 900),
                    ]);
                }
            }

            // One format for the database.
            $deadline = date('Y-m-d H:i:s', $deadlineAt);
        }

        try {
            $phase = $this->m->format($tid)->phase($phaseKey);
            $slots = $this->m->format($tid)->drawSlots($phaseKey);
            $pool = $this->m->drawPool($tid, $phaseKey);
        } catch (Exception|TypeError $e) {
            // ZF2 flashed any failure of the format lookup; a TypeError comes from odd format JSON.
            throw new ActionRefused('tournament::admin.prepare.error', ['message' => $e->getMessage()]);
        }

        if (count($pool) !== count($slots)) {
            throw new ActionRefused('tournament::admin.prepare.pool_mismatch', ['pool' => count($pool), 'slots' => count($slots)]);
        }

        try {
            if ($round < 1) {
                $round = $this->beacon->roundAt($now + 86400);
            }

            if ($isTest) {
                // A published round lifts the assurance, which is exactly right for a test tournament: there
                // is nothing to defend there. A real tournament keeps a future round.
                $round = $this->beacon->roundAt($now - 120);
            } else {
                $this->beacon->assertFutureRound($round);
            }

            $drawAt = $this->beacon->roundTime($round);
        } catch (RoundNotFutureException $e) {
            throw new ActionRefused('draw::admin.round_not_future', ['round' => $e->round, 'current' => $e->current]);
        } catch (RuntimeException $e) {
            throw new ActionRefused($isTest ? 'tournament::admin.prepare.beacon' : 'tournament::admin.prepare.beacon_manual', ['message' => $e->getMessage()]);
        }

        // Tokens carry the phase key as prefix (AC-01, DG-01): "I have T-03" would be ambiguous across draws.
        $tokenNames = $this->tokens->tokenNames(count($slots), (string) $phase['key']);
        $payload = $this->tokens->payload($tokenNames, $slots, sprintf(self::RULE, $round));
        $this->guard->assertVerifierSafe($payload);

        $serverSeed = $this->draws->generateServerSeed();
        $commitment = $this->draws->commit($payload, $serverSeed);
        $slug = $this->commits->uniqueSlug();
        $title = mb_substr(sprintf(self::TITLE, $t->title, $phase['label']), 0, 160);

        $draw = DB::transaction(function () use ($tid, $slug, $title, $payload, $commitment, $round, $deadline, $serverSeed, $by, $tokenNames, $pool): Draw {
            $draw = Draw::query()->create([
                'tid' => $tid,
                'slug' => $slug,
                'title' => $title,
                'status' => 'committed',
                'mode' => 'token',
                'payload' => $this->draws->canonical($payload),
                'commitment' => $commitment,
                'beacon_source' => $this->beacon->sourceLabel($round),
                'pick_deadline' => $deadline !== '' ? $deadline : null,
                'server_seed' => $serverSeed,
                'created_uid' => (int) $by->uid,
                'created' => now()->format('Y-m-d H:i:s'),
            ]);

            foreach ($tokenNames as $token) {
                DrawToken::query()->create(['did' => (int) $draw->did, 'position' => $token['position'], 'token' => $token['token']]);
            }

            foreach ($pool as $player) {
                DrawEligible::query()->create(['did' => (int) $draw->did, 'tpid' => (int) $player['tpid']]);
            }

            return $draw;
        });

        $flashes = [[
            'type' => 'success',
            'text' => (string) __('tournament::admin.prepare.created', [
                'tokens' => count($tokenNames),
                'eligible' => count($pool),
                'draw_at' => date('d.m.Y H:i', $drawAt),
                'round' => $round,
                'deadline' => $deadline !== '' ? date('d.m.Y H:i', (int) strtotime($deadline)) : (string) __('tournament::admin.prepare.deadline_open'),
                'url' => route('draw.record', ['slug' => $slug]),
            ]),
        ]];

        // Test tournament: draw at once and open the picking phase. The order is mandatory: execute first,
        // before that no token has a slot. A real tournament keeps both apart (announcement in between).
        if ($isTest) {
            try {
                $flashes[] = ['type' => 'success', 'text' => $this->executor->execute($draw)];
                $executed = Draw::query()->find((int) $draw->did);

                if ($executed === null || $executed->status !== 'picking' || ! $executed->final_seed) {
                    throw new RuntimeException((string) __('tournament::admin.prepare.not_opened'));
                }

                $flashes[] = ['type' => 'success', 'text' => (string) __('tournament::admin.prepare.test_opened')];
            } catch (ActionRefused $e) {
                $flashes[] = ['type' => 'error', 'text' => (string) __('tournament::admin.prepare.test_failed', ['message' => (string) __($e->key, $e->replace)])];
            } catch (Exception $e) {
                $flashes[] = ['type' => 'error', 'text' => (string) __('tournament::admin.prepare.test_failed', ['message' => $e->getMessage()])];
            }
        }

        return ['draw' => $draw->fresh() ?? $draw, 'flashes' => $flashes];
    }
}
