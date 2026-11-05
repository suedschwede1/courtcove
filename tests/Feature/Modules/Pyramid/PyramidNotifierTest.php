<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Mail\PyramidNotificationMail;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Services\PyramidNotifier;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsPyramidLadders;
use Tests\TestCase;

class PyramidNotifierTest extends TestCase
{
    use BuildsPyramidLadders;
    use LazilyRefreshDatabase;

    private const TYPES = [
        'challenge_created', 'scheduled', 'result_entered', 'declined', 'withdrawn', 'deadline_extended',
        'forfeited', 'expired', 'result_corrected', 'admitted', 'released',
    ];

    /**
     * @return array{0: PyramidChallenge, 1: PyramidPlayer, 2: PyramidPlayer, 3: User, 4: User}
     */
    private function challengeWithUsers(array $attributes = []): array
    {
        $ladder = $this->makeLadder(8);
        $challenger = $this->playerAt($ladder, 8);
        $opponent = $this->playerAt($ladder, 5);
        $challengerUser = User::factory()->create(['email' => 'eight@example.test']);
        $opponentUser = User::factory()->create(['email' => 'five@example.test']);
        $challenger->update(['uid' => $challengerUser->uid]);
        $opponent->update(['uid' => $opponentUser->uid]);

        $challenge = PyramidChallenge::factory()->create(array_merge([
            'ladder_id' => $ladder->id,
            'challenger_id' => $challenger->id,
            'opponent_id' => $opponent->id,
            'deadline' => '2026-11-11',
        ], $attributes));

        return [$challenge, $challenger->refresh(), $opponent->refresh(), $challengerUser, $opponentUser];
    }

    private function notifier(): PyramidNotifier
    {
        return $this->app->make(PyramidNotifier::class);
    }

    #[Test]
    public function challenge_mail_goes_to_the_opponent(): void
    {
        Mail::fake();
        [$challenge, , , , $opponentUser] = $this->challengeWithUsers();

        $this->notifier()->challengeCreated($challenge->id);

        Mail::assertSent(PyramidNotificationMail::class, 1);
        Mail::assertSent(PyramidNotificationMail::class, function (PyramidNotificationMail $mail) use ($opponentUser) {
            return $mail->hasTo($opponentUser->email)
                && $mail->subjectLine === 'Du wurdest gefordert'
                && $mail->envelope()->subject === 'Forderungspyramide: Du wurdest gefordert'
                && str_contains(
                    $mail->render(),
                    "Spieler 8 hat dich in der Forderungspyramide gefordert.\n\nBitte vereinbart möglichst rasch einen Termin. Frist: 11.11.2026.\n"
                );
        });
    }

    #[Test]
    public function scheduled_mail_goes_to_both(): void
    {
        Mail::fake();
        [$challenge, , , $challengerUser, $opponentUser] = $this->challengeWithUsers([
            'status' => 'scheduled',
            'scheduled_at' => '2026-11-09 18:30:00',
        ]);

        $this->notifier()->scheduled($challenge->id);

        Mail::assertSent(PyramidNotificationMail::class, 2);
        Mail::assertSent(PyramidNotificationMail::class, fn ($mail) => $mail->hasTo($challengerUser->email)
            && $mail->envelope()->subject === 'Forderungspyramide: Termin für dein Forderungsspiel'
            && str_contains($mail->render(), 'Das Forderungsspiel Spieler 8 gegen Spieler 5 findet am 09.11.2026 18:30 Uhr statt.'));
        Mail::assertSent(PyramidNotificationMail::class, fn ($mail) => $mail->hasTo($opponentUser->email));
    }

    #[Test]
    public function scheduled_without_a_date_sends_nothing(): void
    {
        Mail::fake();
        [$challenge] = $this->challengeWithUsers();

        $this->notifier()->scheduled($challenge->id);

        Mail::assertNothingSent();
    }

    #[Test]
    public function result_mail_goes_to_the_party_that_did_not_enter_it(): void
    {
        Mail::fake();
        [$challenge, $challenger, , $challengerUser, $opponentUser] = $this->challengeWithUsers([
            'status' => 'played',
            'score' => '6:4 6:3',
            'winner_id' => null,
            'entered_uid' => null,
        ]);
        $challenge->update(['winner_id' => $challenger->id, 'entered_uid' => $challengerUser->uid]);

        $this->notifier()->resultEntered($challenge->id);

        Mail::assertSent(PyramidNotificationMail::class, 1);
        Mail::assertSent(PyramidNotificationMail::class, fn ($mail) => $mail->hasTo($opponentUser->email)
            && str_contains($mail->render(), "Für dein Forderungsspiel Spieler 8 gegen Spieler 5 wurde das Ergebnis 6:4 6:3 eingetragen, Sieger: Spieler 8.\n\nStimmt das nicht, bitte beim Ranglistenverantwortlichen melden.\n"));

        Mail::fake();
        $challenge->update(['entered_uid' => $opponentUser->uid]);
        $this->notifier()->resultEntered($challenge->id);
        Mail::assertSent(PyramidNotificationMail::class, fn ($mail) => $mail->hasTo($challengerUser->email));
        Mail::assertSent(PyramidNotificationMail::class, 1);

        Mail::fake();
        $challenge->update(['entered_uid' => 999999]);
        $this->notifier()->resultEntered($challenge->id);
        Mail::assertSent(PyramidNotificationMail::class, fn ($mail) => $mail->hasTo($challengerUser->email));
    }

    #[Test]
    public function decline_reasons_map_to_the_old_wording(): void
    {
        [$challenge, , , $challengerUser] = $this->challengeWithUsers(['status' => 'declined']);

        foreach (['other_match' => 'hat noch ein anderes Forderungsspiel', 'injury' => 'ist verletzt oder krank', null => 'hat abgelehnt'] as $reason => $text) {
            Mail::fake();
            $challenge->update(['decline_reason' => $reason === '' ? null : $reason]);
            $this->notifier()->declined($challenge->id);
            Mail::assertSent(PyramidNotificationMail::class, fn ($mail) => $mail->hasTo($challengerUser->email)
                && str_contains($mail->render(), "Spieler 5 {$text} und hat deine Forderung abgelehnt (Regel 5.2). Du bist wieder frei zu fordern.\n"));
        }
    }

    #[Test]
    public function withdrawn_and_notes_reach_the_right_people(): void
    {
        Mail::fake();
        [$challenge, , , $challengerUser, $opponentUser] = $this->challengeWithUsers([
            'status' => 'forfeited',
            'winner_id' => null,
        ]);

        $this->notifier()->withdrawn($challenge->id);
        Mail::assertSent(PyramidNotificationMail::class, 1);
        Mail::assertSent(PyramidNotificationMail::class, fn ($mail) => $mail->hasTo($opponentUser->email)
            && $mail->envelope()->subject === 'Forderungspyramide: Forderung zurückgezogen'
            && str_contains($mail->render(), "Spieler 8 hat die Forderung gegen dich zurückgezogen. Du bist wieder frei.\n"));

        Mail::fake();
        $this->notifier()->deadlineExtended($challenge->id, 'Krankheit');
        Mail::assertSent(PyramidNotificationMail::class, 2);
        Mail::assertSent(PyramidNotificationMail::class, fn ($mail) => $mail->hasTo($challengerUser->email)
            && str_contains($mail->render(), "Die Frist für euer Forderungsspiel wurde bis 11.11.2026 verlängert.\nGrund: Krankheit\n"));

        Mail::fake();
        $this->notifier()->expired($challenge->id, 'Keine Meldung');
        Mail::assertSent(PyramidNotificationMail::class, 2);
        Mail::assertSent(PyramidNotificationMail::class, fn ($mail) => str_contains($mail->render(), "Euer Forderungsspiel wurde ohne Wertung geschlossen.\nGrund: Keine Meldung\n"));

        Mail::fake();
        $this->notifier()->resultCorrected($challenge->id, 'Tippfehler');
        Mail::assertSent(PyramidNotificationMail::class, 2);
        Mail::assertSent(PyramidNotificationMail::class, fn ($mail) => str_contains($mail->render(), "Das Ergebnis eures Forderungsspiels wurde richtiggestellt.\nGrund: Tippfehler\n"));

        Mail::fake();
        $challenge->update(['winner_id' => $challenge->opponent_id]);
        $this->notifier()->forfeited($challenge->id, 'Nicht erschienen');
        Mail::assertSent(PyramidNotificationMail::class, 2);
        Mail::assertSent(PyramidNotificationMail::class, fn ($mail) => str_contains($mail->render(), "Euer Forderungsspiel wurde kampflos gewertet, Sieger: Spieler 5.\nGrund: Nicht erschienen\n"));
    }

    #[Test]
    public function admission_and_release_mails_go_to_the_player(): void
    {
        Mail::fake();
        [, $challenger, , $challengerUser] = $this->challengeWithUsers();

        $this->notifier()->admittedFromWaitlist($challenger->id, 9);
        Mail::assertSent(PyramidNotificationMail::class, fn ($mail) => $mail->hasTo($challengerUser->email)
            && $mail->envelope()->subject === 'Forderungspyramide: In die Rangliste aufgenommen'
            && str_contains($mail->render(), "Du wurdest in die Rangliste aufgenommen, auf Platz 9.\n"));

        $this->notifier()->releasedToEnter($challenger->id);
        Mail::assertSent(PyramidNotificationMail::class, fn ($mail) => $mail->hasTo($challengerUser->email)
            && str_contains($mail->render(), "Du darfst jetzt einen aktiven Spieler der Rangliste einfordern (Regel 6.1). Wähle einen Gegner, gegen den du realistische Chancen hast.\n"));
    }

    #[Test]
    public function players_without_uid_or_email_get_nothing(): void
    {
        Mail::fake();
        [$challenge, $challenger, $opponent, $challengerUser, $opponentUser] = $this->challengeWithUsers();

        // Column email is not a recipient; no uid means no mail.
        $opponent->update(['uid' => null, 'email' => 'column@example.test']);
        // Empty address on the account.
        $challengerUser->forceFill(['email' => ''])->save();

        $this->notifier()->challengeCreated($challenge->id);
        $this->notifier()->scheduled($challenge->id);
        $this->notifier()->withdrawn($challenge->id);

        // uid pointing at a missing users row.
        $opponent->update(['uid' => 987654]);
        $this->notifier()->deadlineExtended($challenge->id, 'x');

        Mail::assertNothingSent();
    }

    #[Test]
    public function every_type_has_a_german_and_english_text(): void
    {
        foreach (self::TYPES as $type) {
            foreach (['de', 'en'] as $locale) {
                foreach (['subject', 'body'] as $part) {
                    $key = "pyramid::mail.{$type}.{$part}";
                    $this->assertTrue(Lang::hasForLocale($key, $locale), "{$key} missing in {$locale}");
                }
            }
        }
        $this->assertTrue(Lang::hasForLocale('pyramid::mail.prefix', 'de'));
    }

    #[Test]
    public function mail_failures_are_logged_and_swallowed(): void
    {
        [$challenge] = $this->challengeWithUsers();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        Log::spy();

        $this->notifier()->withdrawn($challenge->id);

        Log::shouldHaveReceived('error')->with('[Pyramid] Mailversand fehlgeschlagen: smtp down');
    }

    #[Test]
    public function an_english_session_still_sends_german_mails(): void
    {
        Mail::fake();
        app()->setLocale('en');
        [$challenge] = $this->challengeWithUsers();

        $this->notifier()->challengeCreated($challenge->id);

        Mail::assertSent(PyramidNotificationMail::class, fn ($mail) => $mail->envelope()->subject === 'Forderungspyramide: Du wurdest gefordert');
        $this->assertSame('en', app()->getLocale());
    }
}
