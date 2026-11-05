<?php

declare(strict_types=1);

namespace App\Modules\Draw\Services;

use App\Models\User;
use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Models\Draw;
use App\Modules\TournamentBase\Support\ActionRefused;
use Closure;
use RuntimeException;

/**
 * Manual (direct) draw commit, ported from ZF2 Draw AdminController::commitAction.
 *
 * Order as in ZF2: every field check is collected (title, rule, round, at least two entries,
 * entries == slots, duplicate entries, duplicate slots, entry pattern with the first offender
 * only), then numeric entries become int, then the verifier guard, then the beacon round must
 * lie in the future, then one insert. Three checks are new and come after the ZF2 ones: the DB
 * limits of title and tid, and numeric entries that would change in the int cast ("07", values
 * beyond PHP_INT_MAX) and so publish something other than what was entered.
 */
final class DrawCommitService
{
    private const TITLE_MAX = 160;

    private const TID_MAX = 4294967295;

    private readonly Closure $slugSource;

    public function __construct(
        private readonly DrawService $draws,
        private readonly BeaconService $beacon,
        private readonly PayloadGuard $guard,
        ?Closure $slugSource = null,
    ) {
        $this->slugSource = $slugSource ?? static fn (): string => strtolower(substr(bin2hex(random_bytes(8)), 0, 12));
    }

    /**
     * @param  array{title?: string, entries?: string, slots?: string, rule?: string, round?: string, tid?: string}  $input
     *
     * @throws CommitRefused
     */
    public function commitManual(array $input, User $by): Draw
    {
        $title = trim((string) ($input['title'] ?? ''));
        $round = (int) ($input['round'] ?? 0);
        $entries = $this->draws->parseLines((string) ($input['entries'] ?? ''));
        $slots = $this->draws->parseLines((string) ($input['slots'] ?? ''));
        $rule = trim((string) ($input['rule'] ?? ''));
        $tidInput = trim((string) ($input['tid'] ?? ''));

        $errors = [];

        if ($title === '') {
            $errors[] = ['draw::admin.commit.title_missing', []];
        }
        if ($rule === '') {
            $errors[] = ['draw::admin.commit.rule_missing', []];
        }
        if ($round < 1) {
            $errors[] = ['draw::admin.commit.round_invalid', []];
        }
        if (count($entries) < 2) {
            $errors[] = ['draw::admin.commit.min_entries', []];
        }
        if (count($entries) != count($slots)) {
            $errors[] = ['draw::admin.commit.count_mismatch', ['entries' => count($entries), 'slots' => count($slots)]];
        }
        if (count($entries) != count(array_unique($entries))) {
            $errors[] = ['draw::admin.commit.duplicate_entries', []];
        }
        if (count($slots) != count(array_unique($slots))) {
            $errors[] = ['draw::admin.commit.duplicate_slots', []];
        }

        // Direct draws take uids or short placement ids such as A2 or Q3; decimals stay forbidden.
        foreach ($entries as $entry) {
            if (! preg_match('/^([0-9]+|[A-Z][0-9]{1,2})$/', $entry)) {
                $errors[] = ['draw::admin.commit.invalid_entry', ['entry' => $entry]];
                break;
            }
        }

        // New: a number must survive the int cast unchanged (no leading zero, within PHP_INT_MAX).
        foreach ($entries as $entry) {
            if (preg_match('/^[0-9]+$/', $entry) && (string) (int) $entry !== $entry) {
                $errors[] = ['draw::admin.commit.entry_not_canonical', ['entry' => $entry]];
                break;
            }
        }

        if (mb_check_encoding($title, 'UTF-8') && mb_strlen($title) > self::TITLE_MAX) {
            $errors[] = ['draw::admin.commit.title_too_long', ['max' => self::TITLE_MAX]];
        }

        $tid = 0;
        if ($tidInput !== '') {
            if (! ctype_digit($tidInput) || strlen($tidInput) > 10 || (int) $tidInput > self::TID_MAX) {
                $errors[] = ['draw::admin.commit.tid_invalid', []];
            } else {
                $tid = (int) $tidInput;
            }
        }

        if ($errors !== []) {
            throw new CommitRefused($errors);
        }

        // Numeric lots stay integers, placement ids stay strings; both are stable in canonical JSON.
        $payload = [
            'entries' => array_map(static fn (string $entry): int|string => preg_match('/^[0-9]+$/', $entry) ? (int) $entry : $entry, $entries),
            'slots' => $slots,
            'rule' => $rule,
        ];

        try {
            if (! mb_check_encoding($title, 'UTF-8')) {
                throw new ActionRefused('draw::admin.commit.invalid_utf8');
            }
            $this->guard->assertVerifierSafe($payload);
        } catch (ActionRefused $e) {
            throw new CommitRefused([[$e->key, $e->replace]]);
        }

        $serverSeed = $this->draws->generateServerSeed();
        $commitment = $this->draws->commit($payload, $serverSeed);

        try {
            $this->beacon->assertFutureRound($round);
        } catch (RoundNotFutureException $e) {
            throw new CommitRefused([['draw::admin.round_not_future', ['round' => $e->round, 'current' => $e->current]]]);
        } catch (RuntimeException $e) {
            throw new CommitRefused([['draw::admin.commit.beacon_failed', ['message' => $e->getMessage()]]]);
        }

        return Draw::query()->create([
            'tid' => $tid !== 0 ? $tid : null,
            'slug' => $this->uniqueSlug(),
            'title' => $title,
            'status' => 'committed',
            'mode' => 'direct',
            'payload' => $this->draws->canonical($payload),
            'commitment' => $commitment,
            'beacon_source' => $this->beacon->sourceLabel($round),
            'server_seed' => $serverSeed,
            'created_uid' => (int) $by->uid,
            'created' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /** Short collision-free id for the public record URL (12 lowercase hex chars, 20 attempts). */
    public function uniqueSlug(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $slug = ($this->slugSource)();

            if (! Draw::query()->where('slug', $slug)->exists()) {
                return $slug;
            }
        }

        throw new RuntimeException('could not generate a unique slug');
    }
}
