<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Services;

use App\Models\User;
use App\Modules\Pyramid\Mail\PyramidNotificationMail;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Plain-text mail notifications for challenges (port of the old NotificationService).
 *
 * A failing mail server must never abort the flow: the challenge is booked either way,
 * so every send swallows its exception and writes it to the log.
 * Recipients are players with a uid whose user account has an address; the
 * players.email column is not a recipient.
 */
final class PyramidNotifier
{
    private const LOCALE = 'de';

    public function __construct(private readonly PyramidRepository $repo) {}

    public function challengeCreated(int $challengeId): void
    {
        [$challenge, $challenger, $opponent] = $this->parties($challengeId);

        if (! $challenge) {
            return;
        }

        $this->send($opponent, 'challenge_created', [
            'challenger' => $challenger->label,
            'deadline' => date('d.m.Y', strtotime((string) $challenge->deadline)),
        ]);
    }

    public function scheduled(int $challengeId): void
    {
        [$challenge, $challenger, $opponent] = $this->parties($challengeId);

        if (! $challenge || ! $challenge->scheduled_at) {
            return;
        }

        $replace = [
            'challenger' => $challenger->label,
            'opponent' => $opponent->label,
            'date' => date('d.m.Y H:i', strtotime((string) $challenge->scheduled_at)),
        ];

        $this->send($challenger, 'scheduled', $replace);
        $this->send($opponent, 'scheduled', $replace);
    }

    /**
     * Goes to the party that did NOT enter the result, so a wrong entry gets noticed.
     */
    public function resultEntered(int $challengeId): void
    {
        [$challenge, $challenger, $opponent] = $this->parties($challengeId);

        if (! $challenge) {
            return;
        }

        $recipient = (int) $challenger->uid === (int) $challenge->entered_uid ? $opponent : $challenger;
        $winner = (int) $challenge->winner_id === $challenger->id ? $challenger : $opponent;

        $this->send($recipient, 'result_entered', [
            'challenger' => $challenger->label,
            'opponent' => $opponent->label,
            'score' => (string) $challenge->score,
            'winner' => $winner->label,
        ]);
    }

    /**
     * Rule 5.2: the challenger learns why he is free again.
     */
    public function declined(int $challengeId): void
    {
        [$challenge, $challenger, $opponent] = $this->parties($challengeId);

        if (! $challenge) {
            return;
        }

        $reason = in_array($challenge->decline_reason, ['other_match', 'injury'], true) ? $challenge->decline_reason : 'other';

        $this->send($challenger, 'declined', [
            'opponent' => $opponent->label,
            'reason' => __("pyramid::mail.declined.reason.{$reason}", [], self::LOCALE),
        ]);
    }

    /**
     * The challenged player learns that he is free again.
     */
    public function withdrawn(int $challengeId): void
    {
        [$challenge, $challenger, $opponent] = $this->parties($challengeId);

        if (! $challenge) {
            return;
        }

        $this->send($opponent, 'withdrawn', ['challenger' => $challenger->label]);
    }

    /**
     * Administration extended the deadline; neither party triggered it.
     */
    public function deadlineExtended(int $challengeId, string $note): void
    {
        [$challenge, $challenger, $opponent] = $this->parties($challengeId);

        if (! $challenge) {
            return;
        }

        $replace = [
            'deadline' => date('d.m.Y', strtotime((string) $challenge->deadline)),
            'note' => $note,
        ];

        $this->send($challenger, 'deadline_extended', $replace);
        $this->send($opponent, 'deadline_extended', $replace);
    }

    /**
     * A forfeit moves the ladder without a match having been played.
     */
    public function forfeited(int $challengeId, string $note): void
    {
        [$challenge, $challenger, $opponent] = $this->parties($challengeId);

        if (! $challenge) {
            return;
        }

        $winner = (int) $challenge->winner_id === $challenger->id ? $challenger : $opponent;
        $replace = ['winner' => $winner->label, 'note' => $note];

        $this->send($challenger, 'forfeited', $replace);
        $this->send($opponent, 'forfeited', $replace);
    }

    /**
     * Closed without a result because nobody took care of it.
     */
    public function expired(int $challengeId, string $note): void
    {
        $this->notifyBoth($challengeId, 'expired', $note);
    }

    /**
     * A correction can change the positions of both parties.
     */
    public function resultCorrected(int $challengeId, string $note): void
    {
        $this->notifyBoth($challengeId, 'result_corrected', $note);
    }

    /**
     * The admitted player did not trigger the admission himself.
     */
    public function admittedFromWaitlist(int $playerId, int $position): void
    {
        $this->send($this->repo->player($playerId), 'admitted', ['position' => $position]);
    }

    /**
     * Without this mail the released player would not know he may challenge now.
     */
    public function releasedToEnter(int $playerId): void
    {
        $this->send($this->repo->player($playerId), 'released', []);
    }

    private function notifyBoth(int $challengeId, string $type, string $note): void
    {
        [$challenge, $challenger, $opponent] = $this->parties($challengeId);

        if (! $challenge) {
            return;
        }

        $this->send($challenger, $type, ['note' => $note]);
        $this->send($opponent, $type, ['note' => $note]);
    }

    /**
     * @return array{0: ?PyramidChallenge, 1: ?PyramidPlayer, 2: ?PyramidPlayer}
     */
    private function parties(int $challengeId): array
    {
        $challenge = $this->repo->challenge($challengeId);

        if (! $challenge) {
            return [null, null, null];
        }

        $challenger = $this->repo->player($challenge->challenger_id);
        $opponent = $this->repo->player($challenge->opponent_id);

        if (! $challenger || ! $opponent) {
            return [null, null, null];
        }

        return [$challenge, $challenger, $opponent];
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    private function send(?PyramidPlayer $player, string $type, array $replace): void
    {
        if (! $player || ! $player->uid) {
            return;
        }

        try {
            $user = User::query()->find($player->uid);

            if (! $user || ! $user->email) {
                return;
            }

            Mail::to(new Address($user->email, (string) $user->alias))->send(new PyramidNotificationMail(
                __("pyramid::mail.{$type}.subject", [], self::LOCALE),
                __("pyramid::mail.{$type}.body", $replace, self::LOCALE),
                (string) $user->alias,
            ));
        } catch (\Throwable $e) {
            Log::error('[Pyramid] Mailversand fehlgeschlagen: '.$e->getMessage());
        }
    }
}
