<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Mail\PyramidNotificationMail;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidEvent;
use App\Modules\Pyramid\Models\PyramidGrant;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChallengeActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PyramidLadder $ladder;

    private User $challengerUser;

    private User $opponentUser;

    private User $thirdUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ladder = PyramidLadder::factory()->asDefault()->withRanking(10)->create();
        $this->challengerUser = $this->userAt(8, '+43 000 000 0008');
        $this->opponentUser = $this->userAt(5, '+43 000 000 0005');
        $this->thirdUser = $this->userAt(3, null);
    }

    private function userAt(int $position, ?string $phone): User
    {
        $user = User::factory()->create(['email' => 'spieler'.$position.'@example.test']);
        PyramidPlayer::query()->where('ladder_id', $this->ladder->id)->where('position', $position)
            ->update(['uid' => $user->uid, 'phone' => $phone]);

        return $user;
    }

    private function challenge(string $status = 'open', array $attributes = []): PyramidChallenge
    {
        $challenger = PyramidPlayer::query()->where('ladder_id', $this->ladder->id)->where('position', 8)->firstOrFail();
        $opponent = PyramidPlayer::query()->where('ladder_id', $this->ladder->id)->where('position', 5)->firstOrFail();

        return PyramidChallenge::factory()->create($attributes + [
            'ladder_id' => $this->ladder->id,
            'challenger_id' => $challenger->id,
            'opponent_id' => $opponent->id,
            'challenger_pos' => 8,
            'opponent_pos' => 5,
            'status' => $status,
            'scheduled_at' => $status === 'scheduled' ? now()->addDays(2)->format('Y-m-d H:i:s') : null,
        ]);
    }

    private function url(PyramidChallenge $challenge, string $action = ''): string
    {
        return '/pyramide/forderung/'.$challenge->id.($action === '' ? '' : '/'.$action);
    }

    #[Test]
    public function unknown_challenge_is_a_404_with_the_old_text(): void
    {
        $response = $this->get('/pyramide/forderung/9999');

        $response->assertNotFound();
        $this->assertSame('Diese Forderung gibt es nicht.', $response->exception->getMessage());
    }

    #[Test]
    public function contact_is_visible_only_to_the_other_side_while_running(): void
    {
        $open = $this->challenge('open');

        $this->actingAs($this->challengerUser)->get($this->url($open))->assertOk()
            ->assertSee('+43 000 000 0005')
            ->assertSee('tel:', false)
            ->assertSee('https://wa.me/430000000005', false)
            ->assertDontSee('+43 000 000 0008');

        $this->actingAs($this->opponentUser)->get($this->url($open))->assertOk()
            ->assertSee('+43 000 000 0008')
            ->assertDontSee('+43 000 000 0005');

        $this->actingAs($this->thirdUser)->get($this->url($open))->assertOk()
            ->assertDontSee('+43 000 000 0005')
            ->assertDontSee('+43 000 000 0008');

        auth()->logout();
        $this->get($this->url($open))->assertOk()->assertDontSee('+43 000 000');

        $open->update(['status' => 'played', 'winner_id' => $open->opponent_id, 'score' => '6:3 6:4', 'resolved' => '2020-01-05 12:00:00']);

        $this->actingAs($this->challengerUser)->get($this->url($open))->assertOk()
            ->assertDontSee('+43 000 000 0005')
            ->assertSee('6:3 6:4');
    }

    #[Test]
    public function an_admin_who_is_not_involved_sees_both_numbers(): void
    {
        $holder = User::factory()->create();
        PyramidGrant::query()->create(['ladder_id' => $this->ladder->id, 'uid' => $holder->uid, 'role' => 'admin', 'created' => '2027-01-01 00:00:00']);
        PyramidPlayer::query()->where('ladder_id', $this->ladder->id)->where('position', 5)->update(['phone' => null]);
        $open = $this->challenge('open');

        $this->actingAs($holder)->get($this->url($open))->assertOk()
            ->assertSee('+43 000 000 0008')
            ->assertSee('(keine Nummer)');
    }

    #[Test]
    public function page_escapes_names_and_survives_a_broken_stored_date(): void
    {
        PyramidPlayer::query()->where('ladder_id', $this->ladder->id)->where('position', 8)->update(['label' => '<b>Eve</b>']);
        $open = $this->challenge('open', ['deadline' => 'kaputt']);

        $this->get($this->url($open))->assertOk()
            ->assertDontSee('<b>Eve</b>', false)
            ->assertSee('&lt;b&gt;Eve&lt;/b&gt;', false);
    }

    #[Test]
    public function outsider_cannot_act(): void
    {
        $scheduled = $this->challenge('scheduled');

        foreach (['termin' => ['scheduled_at' => now()->addDay()->format('Y-m-d\TH:i')], 'ergebnis' => ['s1a' => 6, 's1b' => 4, 's2a' => 6, 's2b' => 4], 'ablehnen' => ['reason' => 'injury'], 'zurueckziehen' => []] as $action => $data) {
            $this->actingAs($this->thirdUser)->post($this->url($scheduled, $action), $data)->assertForbidden();
        }

        $this->actingAs(User::factory()->create())->post($this->url($scheduled, 'termin'))->assertForbidden();
        $this->assertSame('scheduled', $scheduled->fresh()->status);
        $this->assertSame(0, PyramidEvent::query()->count());
    }

    #[Test]
    public function guests_are_sent_to_the_login_for_actions(): void
    {
        $open = $this->challenge('open');

        $this->post($this->url($open, 'termin'))->assertRedirect();
        $this->post('/pyramide/anmelden', ['ladderId' => $this->ladder->id])->assertRedirect();
        $this->assertSame('open', $open->fresh()->status);
    }

    #[Test]
    public function schedule_stores_the_date_and_mails_both_sides(): void
    {
        Mail::fake();
        $open = $this->challenge('open', ['deadline' => now()->addDays(10)->format('Y-m-d')]);
        $when = now()->addDays(3)->setTime(17, 0);

        $this->actingAs($this->challengerUser)->post($this->url($open, 'termin'), ['scheduled_at' => $when->format('Y-m-d\TH:i')])
            ->assertRedirect($this->url($open))
            ->assertSessionHas('success', 'Termin eingetragen.')
            ->assertSessionMissing('info');

        $fresh = $open->fresh();
        $this->assertSame('scheduled', $fresh->status);
        $this->assertSame($when->format('Y-m-d H:i:s'), $fresh->scheduled_at);
        $this->assertSame('schedule', PyramidEvent::query()->firstOrFail()->type);
        Mail::assertSent(PyramidNotificationMail::class, 2);
    }

    #[Test]
    public function schedule_after_deadline_adds_a_notice(): void
    {
        $open = $this->challenge('open', ['deadline' => now()->addDay()->format('Y-m-d')]);
        $when = now()->addDays(5)->setTime(17, 0);

        $this->actingAs($this->challengerUser)->post($this->url($open, 'termin'), ['scheduled_at' => $when->format('Y-m-d\TH:i')])
            ->assertSessionHas('success', 'Termin eingetragen.')
            ->assertSessionHas('info', 'Der Termin liegt nach der Frist vom '.now()->addDay()->format('d.m.Y').'. Das ist zulässig, die Forderung erscheint aber als überfällig.');
        $this->assertSame('scheduled', $open->fresh()->status);
    }

    #[Test]
    public function schedule_rejects_past_and_garbage_dates(): void
    {
        $open = $this->challenge('open');

        $this->actingAs($this->challengerUser)->post($this->url($open, 'termin'), ['scheduled_at' => '2020-01-01T10:00'])
            ->assertRedirect($this->url($open))
            ->assertSessionHas('error', 'Der Spieltermin liegt in der Vergangenheit.');
        $this->actingAs($this->challengerUser)->post($this->url($open, 'termin'), ['scheduled_at' => 'nonsense'])
            ->assertSessionHas('error', 'Bitte Datum und Uhrzeit angeben, zum Beispiel 14.08.2026 17:00.');
        $this->assertSame('open', $open->fresh()->status);
    }

    #[Test]
    public function result_is_recorded_and_the_other_side_is_mailed(): void
    {
        $recipients = [];
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$recipients): void {
            $recipients[] = $event->message->getTo()[0]->getAddress();
        });
        $scheduled = $this->challenge('scheduled');

        $this->actingAs($this->challengerUser)->post($this->url($scheduled, 'ergebnis'), ['s1a' => 6, 's1b' => 4, 's2a' => 6, 's2b' => 3])
            ->assertRedirect('/pyramide/'.$this->ladder->id)
            ->assertSessionHas('success', 'Ergebnis eingetragen, die Rangliste ist aktualisiert.');

        $fresh = $scheduled->fresh();
        $this->assertSame('played', $fresh->status);
        $this->assertSame('6:4 6:3', $fresh->score);
        $this->assertSame((int) $fresh->challenger_id, (int) $fresh->winner_id);
        $this->assertSame(5, (int) PyramidPlayer::query()->findOrFail($fresh->challenger_id)->position);
        $this->assertSame(['spieler5@example.test'], $recipients);
    }

    #[Test]
    public function invalid_result_shows_the_score_message(): void
    {
        $scheduled = $this->challenge('scheduled');

        $response = $this->actingAs($this->challengerUser)->from($this->url($scheduled))
            ->post($this->url($scheduled, 'ergebnis'), ['s1a' => 6, 's1b' => 4]);

        $response->assertRedirect($this->url($scheduled))
            ->assertSessionHasInput('s1a', '6')
            ->assertSessionHasInput('s1b', '4');
        $this->assertSame(__('tournament-base::score.sets_incomplete', [], 'de'), session('error'));
        $this->assertSame('scheduled', $scheduled->fresh()->status);

        $this->followingRedirects()->actingAs($this->challengerUser)->get($this->url($scheduled))->assertOk()
            ->assertSee('value="6"', false);
    }

    #[Test]
    public function result_form_shows_the_score_message_for_the_kept_input(): void
    {
        $scheduled = $this->challenge('scheduled');

        $this->actingAs($this->challengerUser)
            ->withSession(['_old_input' => ['s1a' => '6', 's1b' => '4', 's2a' => '9', 's2b' => '0']])
            ->get($this->url($scheduled))
            ->assertOk()
            ->assertSee(__('tournament-base::score.describe.set2_invalid'));
    }

    #[Test]
    public function only_the_opponent_declines_and_only_with_a_reason(): void
    {
        Mail::fake();
        $open = $this->challenge('open');

        $this->actingAs($this->challengerUser)->post($this->url($open, 'ablehnen'), ['reason' => 'injury'])->assertForbidden();
        $this->assertSame('open', $open->fresh()->status);

        $this->actingAs($this->opponentUser)->post($this->url($open, 'ablehnen'), ['reason' => 'lazy'])
            ->assertRedirect($this->url($open))
            ->assertSessionHas('error', 'Bitte einen der zulässigen Gründe angeben (Regel 5.2).');
        $this->assertSame('open', $open->fresh()->status);

        $this->actingAs($this->opponentUser)->post($this->url($open, 'ablehnen'), ['reason' => 'injury'])
            ->assertRedirect('/pyramide/'.$this->ladder->id)
            ->assertSessionHas('success', 'Forderung abgelehnt.');

        $fresh = $open->fresh();
        $this->assertSame('declined', $fresh->status);
        $this->assertSame('injury', $fresh->decline_reason);
        $this->assertNotNull($fresh->resolved);
        Mail::assertSent(PyramidNotificationMail::class, 1);
    }

    #[Test]
    public function english_session_still_stores_german_event_text(): void
    {
        $open = $this->challenge('open');

        $this->actingAs($this->opponentUser)->withUnencryptedCookie('locale', 'en')
            ->post($this->url($open, 'ablehnen'), ['reason' => 'other_match'])
            ->assertRedirect();

        $event = PyramidEvent::query()->where('type', 'admin')->firstOrFail();
        $this->assertSame('{"message":"Eine Forderung wurde mit Grund abgelehnt."}', $event->payload);
    }

    #[Test]
    public function only_the_challenger_withdraws(): void
    {
        Mail::fake();
        $open = $this->challenge('open');

        $this->actingAs($this->opponentUser)->post($this->url($open, 'zurueckziehen'))->assertForbidden();
        $this->assertSame('open', $open->fresh()->status);

        $this->actingAs($this->challengerUser)->post($this->url($open, 'zurueckziehen'))
            ->assertRedirect('/pyramide/'.$this->ladder->id)
            ->assertSessionHas('success', 'Forderung zurückgezogen.');

        $this->assertSame('withdrawn', $open->fresh()->status);
        // Default json_encode escapes the umlaut, exactly as the old application wrote it.
        $this->assertSame('{"message":"Eine Forderung wurde zur\u00fcckgezogen."}', PyramidEvent::query()->where('type', 'admin')->firstOrFail()->payload);
        Mail::assertSent(PyramidNotificationMail::class, 1);
    }

    #[Test]
    public function stale_form_after_resolution_redirects_with_a_message(): void
    {
        $played = $this->challenge('played', ['winner_id' => 1, 'score' => '6:0 6:0', 'resolved' => '2020-01-05 12:00:00']);

        foreach (['termin', 'ergebnis', 'ablehnen', 'zurueckziehen'] as $action) {
            $this->actingAs($this->opponentUser)->post($this->url($played, $action), ['reason' => 'injury'])
                ->assertRedirect($this->url($played))
                ->assertSessionHas('error', 'Diese Forderung ist bereits abgeschlossen.');
        }

        $this->assertSame('played', $played->fresh()->status);
    }

    #[Test]
    public function closed_ladder_turns_an_action_into_a_message(): void
    {
        $open = $this->challenge('open');
        $this->ladder->update(['status' => 'closed']);

        $this->actingAs($this->challengerUser)->post($this->url($open, 'zurueckziehen'))
            ->assertRedirect($this->url($open))
            ->assertSessionHas('error', __('pyramid::check.ladder_closed', [], 'de'));
        $this->assertSame('open', $open->fresh()->status);
    }

    #[Test]
    public function join_puts_the_user_on_the_waiting_list_once(): void
    {
        $user = User::factory()->create();
        $user->setMeta('firstname', 'Demo');
        $user->setMeta('lastname', 'Gast');

        $this->actingAs($user)->post('/pyramide/anmelden', ['ladderId' => $this->ladder->id, 'itn' => '7,5'])
            ->assertRedirect('/pyramide/'.$this->ladder->id)
            ->assertSessionHas('success', 'Anmeldung eingetragen. Die Verwaltung nimmt dich in die Pyramide auf.');

        $player = PyramidPlayer::query()->where('uid', $user->uid)->firstOrFail();
        $this->assertSame('waiting', $player->status);
        $this->assertSame('Demo Gast', $player->label);
        $this->assertNull($player->position);
        $this->assertEqualsWithDelta(7.5, (float) $player->itn, 0.001);
        $this->assertEqualsWithDelta(7.5, (float) $player->itn_rated, 0.001);
        $event = PyramidEvent::query()->where('type', 'join')->firstOrFail();
        $this->assertSame('{"player":'.$player->id.'}', $event->payload);

        $this->actingAs($user)->post('/pyramide/anmelden', ['ladderId' => $this->ladder->id])
            ->assertRedirect('/pyramide/'.$this->ladder->id)
            ->assertSessionHas('info', 'Du bist bereits angemeldet.');
        $this->assertSame(1, PyramidPlayer::query()->where('uid', $user->uid)->count());
    }

    #[Test]
    public function join_without_itn_uses_the_ladder_default_and_an_unknown_ladder_is_a_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/pyramide/anmelden', ['ladderId' => 9999])->assertNotFound();

        $this->actingAs($user)->post('/pyramide/anmelden', ['ladderId' => $this->ladder->id])->assertRedirect();
        $player = PyramidPlayer::query()->where('uid', $user->uid)->firstOrFail();
        $this->assertNull($player->itn);
        $this->assertEqualsWithDelta(10.0, (float) $player->itn_rated, 0.001);
    }
}
