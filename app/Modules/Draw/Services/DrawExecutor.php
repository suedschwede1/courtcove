<?php

declare(strict_types=1);

namespace App\Modules\Draw\Services;

use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\TournamentBase\Support\ActionRefused;
use Exception;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reveal: fetches the announced beacon round, runs the draw and releases the result
 * (ported from ZF2 Draw AdminController::executeAction).
 *
 * Order: commit -> execute -> picking -> close. A token draw goes straight to 'picking' (every player
 * learns the group on the click; the assignment stays hidden until close), a direct draw to 'executed'.
 * final_seed, not the status, marks an executed draw. The update is unconditional on did as in ZF2:
 * the pre-check refuses a second execute, and a concurrent duplicate writes identical deterministic values.
 * A stored payload that does not decode or no longer matches its commitment throws (tamper alarm).
 */
final class DrawExecutor
{
    public function __construct(private readonly DrawService $draws, private readonly BeaconService $beacon) {}

    /**
     * @return string the success flash
     *
     * @throws ActionRefused
     * @throws RuntimeException
     */
    public function execute(Draw $draw): string
    {
        if ($draw->discarded !== null && $draw->discarded !== '') {
            throw new ActionRefused('draw::admin.execute.not_found');
        }

        if ($draw->final_seed !== null && $draw->final_seed !== '') {
            throw new ActionRefused('draw::admin.execute.already');
        }

        if ($draw->mode === 'token' && $draw->status !== 'committed') {
            throw new ActionRefused('draw::admin.execute.wrong_state', ['status' => (string) $draw->status]);
        }

        if (! preg_match('/^drand:round:([0-9]+)$/', (string) $draw->beacon_source, $match)) {
            throw new ActionRefused('draw::admin.execute.unknown_source', ['source' => (string) $draw->beacon_source]);
        }

        $round = (int) $match[1];
        $beacon = $this->beacon->round($round);

        if ($beacon === null) {
            throw new ActionRefused('draw::admin.execute.unpublished', ['round' => $round, 'hint' => $this->waitHint($round)]);
        }

        $payload = json_decode((string) $draw->payload, true);

        if (! is_array($payload)) {
            throw new RuntimeException('stored payload is not valid JSON');
        }

        // Safety net: the stored commitment must match the payload, otherwise the database was altered.
        if (! $this->draws->verifyCommitment($payload, (string) $draw->server_seed, (string) $draw->commitment)) {
            throw new RuntimeException('commitment mismatch - payload or seed was altered after the commit');
        }

        $result = $this->draws->execute($payload, (string) $draw->server_seed, $beacon['randomness']);
        $isToken = $draw->mode === 'token';
        $did = (int) $draw->did;

        DB::transaction(function () use ($did, $isToken, $beacon, $result): void {
            Draw::query()->whereKey($did)->update([
                'status' => $isToken ? 'picking' : 'executed',
                'beacon_value' => $beacon['randomness'],
                'final_seed' => $result['final_seed'],
                'result' => json_encode($result['assignment'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'executed' => now()->format('Y-m-d H:i:s'),
            ]);

            if (! $isToken) {
                return;
            }

            // assignment is slot => token; the tokens need token => slot (the inverse permutation).
            $byToken = [];
            foreach ($result['assignment'] as $slot => $token) {
                $byToken[(string) $token] = (string) $slot;
            }

            $unassigned = 0;

            foreach (DrawToken::query()->where('did', $did)->get() as $row) {
                $name = (string) $row->token;

                if (! isset($byToken[$name])) {
                    $unassigned++;

                    continue;
                }

                DrawToken::query()->whereKey((int) $row->dtid)->update(['slot' => $byToken[$name]]);
            }

            if ($unassigned > 0) {
                throw new RuntimeException(sprintf('%u Token(s) haben keinen Slot bekommen - Tokenliste und Payload passen nicht zusammen.', $unassigned));
            }
        });

        $url = route('draw.record', ['slug' => (string) $draw->slug]);

        return $isToken
            ? (string) __('draw::admin.execute.success_token', ['url' => $url])
            : (string) __('draw::admin.execute.success_direct', ['url' => $url]);
    }

    /**
     * When the round falls, not just its number. Without the time (beacon unreachable) the message stays short.
     */
    private function waitHint(int $round): string
    {
        try {
            $fallsAt = $this->beacon->roundTime($round);
        } catch (Exception) {
            return '';
        }

        $wait = $fallsAt - now()->getTimestamp();
        $time = date('H:i:s', $fallsAt);

        if ($wait <= 0) {
            return (string) __('draw::admin.execute.hint_due', ['time' => $time]);
        }

        return $wait < 90
            ? (string) __('draw::admin.execute.hint_seconds', ['time' => $time, 'seconds' => $wait])
            : (string) __('draw::admin.execute.hint_minutes', ['time' => $time, 'minutes' => (int) round($wait / 60)]);
    }
}
