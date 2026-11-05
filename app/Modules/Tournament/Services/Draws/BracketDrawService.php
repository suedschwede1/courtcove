<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services\Draws;

use App\Models\User;
use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\DrawCommitService;
use App\Modules\Draw\Services\DrawExecutor;
use App\Modules\Draw\Services\DrawService;
use App\Modules\Draw\Services\PayloadGuard;
use App\Modules\Draw\Services\RoundNotFutureException;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\TournamentBase\Support\ActionRefused;
use Exception;
use RuntimeException;

/**
 * Prepares the direct draw of one knockout pool (ported from ZF2 AdminController::prepareBracketDrawAction).
 *
 * No token picking: not players but placeholders are distributed (W3 = third qualifier of pool w). The
 * target slots carry phase and bracket line (KO:3), so DrawApplier can transfer the result to the line table
 * without implicit assumptions. The insert is a single statement without a transaction, as in ZF2.
 *
 * A test tournament takes a published round, executes at once and applies (ZF2's forwarded execute and
 * applyDraw actions), so the placeholders stand in the bracket right away.
 */
final class BracketDrawService
{
    /** The bracket rule as committed by ZF2: part of the committed payload, byte-identical, never translated. */
    public const RULE = '%s-Pool %s: Die Kennungen werden den vorab festgelegten Rasterlinien zugelost.';

    /** Draw title format of ZF2 (data, not UI; the dash is an en dash as in the old code). */
    public const TITLE = '%s – %s, Pool %s';

    public function __construct(
        private readonly TournamentManager $m,
        private readonly DrawService $draws,
        private readonly BeaconService $beacon,
        private readonly PayloadGuard $guard,
        private readonly DrawCommitService $commits,
        private readonly DrawExecutor $executor,
        private readonly DrawApplier $applier,
    ) {}

    /**
     * @return array{draw: Draw, flashes: list<array{type: string, text: string}>}
     *
     * @throws ActionRefused when nothing was created
     */
    public function prepare(Tournament $t, string $phaseKey, string $pool, int $round, int $leadMinutes, User $by): array
    {
        $tid = (int) $t->tid;
        $isTest = ! empty($t->is_test);
        $now = now()->getTimestamp();

        try {
            $phase = $this->m->format($tid)->phase($phaseKey);
        } catch (RuntimeException $e) {
            throw new ActionRefused('tournament::admin.prepare.error', ['message' => $e->getMessage()]);
        }

        $lines = $phase['lines']['pools'][$pool] ?? [];
        $code = $phase['lines']['pool_codes'][$pool] ?? null;

        if (! is_array($lines) || $lines === [] || ! is_array($code) || empty($code['prefix']) || ! isset($code['start'])) {
            throw new ActionRefused('tournament::admin.prepare.bracket_incomplete');
        }

        if (($phase['draw']['timing'] ?? null) === 'post') {
            try {
                foreach ($phase['qualify'] ?? [] as $rule) {
                    if (! $this->m->phaseComplete($tid, (string) $rule['from'])) {
                        throw new ActionRefused('tournament::admin.prepare.bracket_post');
                    }
                }
            } catch (RuntimeException $e) {
                throw $e instanceof ActionRefused ? $e : new ActionRefused('tournament::admin.prepare.error', ['message' => $e->getMessage()]);
            }
        }

        // Lead time as short as possible, but with the announcement window; a test tournament takes a
        // round that has already fallen.
        try {
            if ($round < 1) {
                $round = $isTest
                    ? $this->beacon->roundAt($now - 120)
                    : $this->beacon->roundAt($now + max(60, $leadMinutes * 60));
            }

            if (! $isTest) {
                $this->beacon->assertFutureRound($round);
            }

            $drawAt = $this->beacon->roundTime($round);
        } catch (RoundNotFutureException $e) {
            throw new ActionRefused('draw::admin.round_not_future', ['round' => $e->round, 'current' => $e->current]);
        } catch (RuntimeException $e) {
            throw new ActionRefused('tournament::admin.prepare.beacon_manual', ['message' => $e->getMessage()]);
        }

        $entries = [];
        $slots = [];
        $start = (int) $code['start'];

        foreach (array_values($lines) as $index => $line) {
            $entries[] = (string) $code['prefix'].($start + $index);
            $slots[] = $phaseKey.':'.(int) $line;
        }

        $payload = ['entries' => $entries, 'slots' => $slots, 'rule' => sprintf(self::RULE, $phaseKey, $pool)];
        $this->guard->assertVerifierSafe($payload);

        $serverSeed = $this->draws->generateServerSeed();

        $draw = Draw::query()->create([
            'tid' => $tid,
            'slug' => $this->commits->uniqueSlug(),
            'title' => mb_substr(sprintf(self::TITLE, $t->title, $phase['label'], strtoupper($pool)), 0, 160),
            'status' => 'committed',
            'mode' => 'direct',
            'payload' => $this->draws->canonical($payload),
            'commitment' => $this->draws->commit($payload, $serverSeed),
            'beacon_source' => $this->beacon->sourceLabel($round),
            'server_seed' => $serverSeed,
            'created_uid' => (int) $by->uid,
            'created' => now()->format('Y-m-d H:i:s'),
        ]);

        $flashes = [[
            'type' => 'success',
            'text' => (string) __('tournament::admin.prepare.bracket_created', ['draw_at' => date('d.m.Y H:i', $drawAt), 'round' => $round]),
        ]];

        if ($isTest) {
            try {
                $flashes[] = ['type' => 'success', 'text' => $this->executor->execute($draw)];
                $count = $this->applier->apply(Draw::query()->findOrFail((int) $draw->did), $tid, $by);
                $flashes[] = ['type' => 'success', 'text' => (string) __('tournament::admin.apply.success', ['count' => $count])];
                $flashes[] = ['type' => 'success', 'text' => (string) __('tournament::admin.prepare.bracket_test_applied')];
            } catch (ActionRefused $e) {
                $flashes[] = ['type' => 'error', 'text' => (string) __('tournament::admin.prepare.bracket_test_failed', ['message' => (string) __($e->key, $e->replace)])];
            } catch (Exception $e) {
                $flashes[] = ['type' => 'error', 'text' => (string) __('tournament::admin.prepare.bracket_test_failed', ['message' => $e->getMessage()])];
            }
        }

        return ['draw' => $draw->fresh() ?? $draw, 'flashes' => $flashes];
    }
}
