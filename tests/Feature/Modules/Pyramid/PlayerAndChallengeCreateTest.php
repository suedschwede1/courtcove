<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Http\Controllers\PlayerController;
use App\Modules\Pyramid\Mail\PyramidNotificationMail;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidEvent;
use App\Modules\Pyramid\Models\PyramidGrant;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class PlayerAndChallengeCreateTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function ladder(int $players = 10, array $attributes = []): PyramidLadder
    {
        return PyramidLadder::factory()->asDefault()->withRanking($players)->create($attributes);
    }

    private function playerAt(PyramidLadder $ladder, int $position): PyramidPlayer
    {
        return PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', $position)->firstOrFail();
    }

    private function viewerAt(PyramidLadder $ladder, int $position): User
    {
        $user = User::factory()->create();
        $this->playerAt($ladder, $position)->update(['uid' => $user->uid]);

        return $user;
    }

    private function resolved(PyramidLadder $ladder, int $winnerPos, int $loserPos, array $attributes = []): PyramidChallenge
    {
        $winner = $this->playerAt($ladder, $winnerPos);
        $loser = $this->playerAt($ladder, $loserPos);

        return PyramidChallenge::factory()->create($attributes + [
            'ladder_id' => $ladder->id,
            'challenger_id' => $loser->id,
            'opponent_id' => $winner->id,
            'challenger_pos' => $loserPos,
            'opponent_pos' => $winnerPos,
            'status' => 'played',
            'winner_id' => $winner->id,
            'score' => '6:3 6:4',
            'resolved' => '2020-01-05 12:00:00',
        ]);
    }

    #[Test]
    public function phone_is_shown_to_admins_only(): void
    {
        $ladder = $this->ladder();
        $target = $this->playerAt($ladder, 5);
        $target->update(['phone' => '+43 000 000 0005']);
        $holder = User::factory()->create();
        PyramidGrant::query()->create(['ladder_id' => $ladder->id, 'uid' => $holder->uid, 'role' => 'admin', 'created' => '2027-01-01 00:00:00']);
        $participant = $this->viewerAt($ladder, 8);

        $this->actingAs($holder)->get('/pyramide/spieler/'.$target->id)->assertOk()->assertSee('+43 000 000 0005');
        $this->actingAs($participant)->get('/pyramide/spieler/'.$target->id)->assertOk()->assertDontSee('+43 000 000 0005');
        auth()->logout();
        $this->get('/pyramide/spieler/'.$target->id)->assertOk()->assertDontSee('+43 000 000 0005');
    }

    #[Test]
    public function unknown_player_is_a_404_with_the_old_text(): void
    {
        $this->ladder();

        $this->get('/pyramide/spieler/9999')->assertNotFound();

        try {
            $this->app->make(PlayerController::class)->show(9999);
            $this->fail('Expected a 404 exception.');
        } catch (NotFoundHttpException $e) {
            $this->assertSame('Diesen Teilnehmer gibt es nicht.', $e->getMessage());
        }
    }

    #[Test]
    public function profile_shows_position_itn_record_history_and_the_challenge_button(): void
    {
        $ladder = $this->ladder();
        $target = $this->playerAt($ladder, 5);
        $target->update(['itn' => '5.8']);
        $this->resolved($ladder, 5, 9, ['resolved' => '2020-01-05 12:00:00']);
        $this->resolved($ladder, 5, 7, ['resolved' => '2020-01-06 12:00:00']);
        $this->resolved($ladder, 2, 5, ['resolved' => '2020-01-07 12:00:00', 'score' => null, 'status' => 'forfeited']);
        $viewer = $this->viewerAt($ladder, 8);

        $response = $this->actingAs($viewer)->get('/pyramide/spieler/'.$target->id)->assertOk();

        $response->assertSee('Platz 5 · ITN 5,8')
            ->assertSee('Siege')
            ->assertSee('Niederlagen')
            ->assertSee('Match-Historie')
            ->assertSee('07.01.2020')
            ->assertSee('nicht angetreten')
            ->assertSee('6:3 6:4')
            ->assertSee('S. 9')
            ->assertSee('Du stehst auf Platz 8 – Platz 5 ist forderbar.')
            ->assertSee('pyramide/fordern/'.$target->id, false);
        $this->assertMatchesRegularExpression('/Siege<\/p>\s*<p class="ds-kpi__value">2</', $response->getContent());
        $this->assertMatchesRegularExpression('/Niederlagen<\/p>\s*<p class="ds-kpi__value">1</', $response->getContent());
    }

    #[Test]
    public function profile_of_a_blocked_target_shows_the_reason_and_no_link(): void
    {
        $ladder = $this->ladder();
        $viewer = $this->viewerAt($ladder, 8);
        $target = $this->playerAt($ladder, 2);

        $this->actingAs($viewer)->get('/pyramide/spieler/'.$target->id)
            ->assertOk()
            ->assertSee('Von Platz 8 darfst du nur die Plätze 4, 5, 6, 7 fordern.')
            ->assertDontSee('pyramide/fordern/'.$target->id, false);
    }

    #[Test]
    public function profile_has_no_challenge_for_guests_non_participants_and_self(): void
    {
        $ladder = $this->ladder();
        $target = $this->playerAt($ladder, 5);
        $viewer = $this->viewerAt($ladder, 8);

        $this->get('/pyramide/spieler/'.$target->id)->assertOk()->assertDontSee('pyramide/fordern/', false)->assertDontSee('Zum Fordern');
        $this->actingAs(User::factory()->create())->get('/pyramide/spieler/'.$target->id)
            ->assertOk()->assertDontSee('pyramide/fordern/', false)->assertDontSee('anmelden');
        $this->actingAs($viewer)->get('/pyramide/spieler/'.$this->playerAt($ladder, 8)->id)
            ->assertOk()->assertDontSee('pyramide/fordern/', false);
    }

    #[Test]
    public function profile_escapes_names_and_survives_a_broken_stored_date(): void
    {
        $ladder = $this->ladder();
        $target = $this->playerAt($ladder, 5);
        $target->update(['label' => '<b>Eve</b>']);
        $this->resolved($ladder, 5, 9, ['resolved' => 'kaputt']);

        $this->get('/pyramide/spieler/'.$target->id)
            ->assertOk()
            ->assertDontSee('<b>Eve</b>', false)
            ->assertSee('&lt;b&gt;Eve&lt;/b&gt;', false);
    }

    #[Test]
    public function guest_challenge_redirects_to_login_with_flash(): void
    {
        $ladder = $this->ladder();
        $target = $this->playerAt($ladder, 5);

        $this->get('/pyramide/fordern/'.$target->id)
            ->assertRedirect()
            ->assertSessionHas('error', 'Zum Fordern musst du angemeldet sein.');
        $this->post('/pyramide/fordern/'.$target->id)->assertRedirect()->assertSessionHas('error', 'Zum Fordern musst du angemeldet sein.');
        $this->assertSame(0, PyramidChallenge::query()->count());
    }

    #[Test]
    public function waiting_player_is_refused(): void
    {
        $ladder = $this->ladder();
        $user = User::factory()->create();
        PyramidPlayer::factory()->waiting()->create(['ladder_id' => $ladder->id, 'uid' => $user->uid, 'label' => 'Wartender']);
        $target = $this->playerAt($ladder, 5);

        $this->actingAs($user)->get('/pyramide/fordern/'.$target->id)->assertForbidden();
        $this->actingAs($user)->post('/pyramide/fordern/'.$target->id)->assertForbidden();
        $this->assertSame(0, PyramidChallenge::query()->count());
    }

    #[Test]
    public function logged_in_non_participant_is_refused_not_sent_to_login(): void
    {
        $ladder = $this->ladder();
        $target = $this->playerAt($ladder, 5);

        $response = $this->actingAs(User::factory()->create())->get('/pyramide/fordern/'.$target->id);

        $response->assertForbidden();
        $response->assertSessionMissing('error');
    }

    #[Test]
    public function unknown_opponent_is_a_404(): void
    {
        $ladder = $this->ladder();
        $viewer = $this->viewerAt($ladder, 8);

        $this->actingAs($viewer)->get('/pyramide/fordern/9999')->assertNotFound();
    }

    #[Test]
    public function confirm_page_shows_both_players_and_the_form(): void
    {
        $ladder = $this->ladder(10, ['days_to_play' => 9]);
        $viewer = $this->viewerAt($ladder, 8);
        $target = $this->playerAt($ladder, 5);

        $this->actingAs($viewer)->get('/pyramide/fordern/'.$target->id)
            ->assertOk()
            ->assertSee('Forderung aussprechen')
            ->assertSee('Spieler 8')
            ->assertSee('Spieler 5')
            ->assertSee('Platz 8')
            ->assertSee('Platz 5')
            ->assertSee('nicht mehr als 9 Tage')
            ->assertSee('type="datetime-local"', false)
            ->assertSee('name="scheduled_at"', false)
            ->assertSee('Verbindlich fordern')
            ->assertSee('Abbrechen')
            ->assertSee('name="_token"', false);
    }

    #[Test]
    public function invalid_target_redirects_with_the_rule_text(): void
    {
        $ladder = $this->ladder();
        $viewer = $this->viewerAt($ladder, 8);
        $target = $this->playerAt($ladder, 2);

        $this->actingAs($viewer)->get('/pyramide/fordern/'.$target->id)
            ->assertRedirect('/pyramide/'.$ladder->id)
            ->assertSessionHas('error', fn (string $text): bool => str_contains($text, 'Von Platz 8 darfst du nur die Plätze 4, 5, 6, 7 fordern.'));

        $this->actingAs($viewer)->post('/pyramide/fordern/'.$target->id)
            ->assertRedirect('/pyramide/'.$ladder->id)
            ->assertSessionHas('error', fn (string $text): bool => str_contains($text, 'Von Platz 8 darfst du nur die Plätze 4, 5, 6, 7 fordern.'));
        $this->assertSame(0, PyramidChallenge::query()->count());
    }

    #[Test]
    public function rule_exception_from_the_service_redirects_with_the_rule_text(): void
    {
        $ladder = $this->ladder();
        $viewer = $this->viewerAt($ladder, 8);
        $target = $this->playerAt($ladder, 5);

        // The controller's pre-check passes; the ladder closes before the locked check inside create().
        PyramidLadder::query()->whereKey($ladder->id)->update(['status' => 'closed']);

        $this->actingAs($viewer)->post('/pyramide/fordern/'.$target->id)
            ->assertRedirect('/pyramide/'.$ladder->id)
            ->assertSessionHas('error', 'Diese Pyramide läuft derzeit nicht.');
    }

    #[Test]
    public function challenge_is_created_and_mailed(): void
    {
        Mail::fake();
        $ladder = $this->ladder();
        $viewer = $this->viewerAt($ladder, 8);
        $opponentUser = $this->viewerAt($ladder, 5);
        $target = $this->playerAt($ladder, 5);

        $response = $this->actingAs($viewer)->post('/pyramide/fordern/'.$target->id);

        $challenge = PyramidChallenge::query()->firstOrFail();
        $response->assertRedirect('/pyramide/forderung/'.$challenge->id)
            ->assertSessionHas('success', 'Forderung ausgesprochen. Vereinbare jetzt bitte selbst einen Termin.');
        $this->assertSame('open', $challenge->status);
        $this->assertSame((int) $target->id, (int) $challenge->opponent_id);
        $this->assertSame((int) $this->playerAt($ladder, 8)->id, (int) $challenge->challenger_id);
        $this->assertSame(1, PyramidEvent::query()->where('type', 'challenge')->where('challenge_id', $challenge->id)->count());

        Mail::assertSent(PyramidNotificationMail::class, 1);
        Mail::assertSent(PyramidNotificationMail::class, fn (PyramidNotificationMail $mail): bool => $mail->hasTo($opponentUser->email));
    }

    #[Test]
    public function the_mail_goes_out_after_the_transaction_has_returned(): void
    {
        $ladder = $this->ladder();
        $viewer = $this->viewerAt($ladder, 8);
        $this->viewerAt($ladder, 5);
        $target = $this->playerAt($ladder, 5);

        $baseline = DB::transactionLevel();
        $levels = [];
        Event::listen(MessageSending::class, function () use (&$levels): void {
            $levels[] = DB::transactionLevel();
        });

        $this->actingAs($viewer)->post('/pyramide/fordern/'.$target->id)->assertRedirect();

        $this->assertSame([$baseline], $levels);
    }

    #[Test]
    public function bad_date_keeps_the_challenge(): void
    {
        Mail::fake();
        $ladder = $this->ladder();
        $viewer = $this->viewerAt($ladder, 8);
        $target = $this->playerAt($ladder, 5);

        $response = $this->actingAs($viewer)->post('/pyramide/fordern/'.$target->id, ['scheduled_at' => 'morgen irgendwann']);

        $challenge = PyramidChallenge::query()->firstOrFail();
        $this->assertSame('open', $challenge->status);
        $this->assertNull($challenge->scheduled_at);
        $response->assertRedirect('/pyramide/forderung/'.$challenge->id)
            ->assertSessionHas('error', fn (string $text): bool => str_ends_with($text, 'Die Forderung ist ausgesprochen, der Termin fehlt noch.')
                && str_contains($text, 'Bitte Datum und Uhrzeit angeben'));
    }

    #[Test]
    public function past_date_keeps_the_challenge_with_the_rule_text(): void
    {
        $ladder = $this->ladder();
        $viewer = $this->viewerAt($ladder, 8);
        $target = $this->playerAt($ladder, 5);

        $this->actingAs($viewer)->post('/pyramide/fordern/'.$target->id, ['scheduled_at' => '2000-01-01T10:00'])
            ->assertSessionHas('error', 'Der Spieltermin liegt in der Vergangenheit. Die Forderung ist ausgesprochen, der Termin fehlt noch.');
        $this->assertSame('open', PyramidChallenge::query()->firstOrFail()->status);
    }

    #[Test]
    public function date_schedules_immediately(): void
    {
        Mail::fake();
        $ladder = $this->ladder();
        $viewer = $this->viewerAt($ladder, 8);
        $this->viewerAt($ladder, 5);
        $target = $this->playerAt($ladder, 5);
        $tomorrow = CarbonImmutable::now()->addDay()->setTime(17, 0);

        $response = $this->actingAs($viewer)->post('/pyramide/fordern/'.$target->id, ['scheduled_at' => $tomorrow->format('Y-m-d\TH:i')]);

        $challenge = PyramidChallenge::query()->firstOrFail();
        $response->assertRedirect('/pyramide/forderung/'.$challenge->id)
            ->assertSessionHas('success', 'Forderung ausgesprochen und Termin eingetragen.');
        $this->assertSame('scheduled', $challenge->status);
        $this->assertSame($tomorrow->format('Y-m-d H:i:s'), $challenge->scheduled_at);

        $event = PyramidEvent::query()->where('type', 'schedule')->where('challenge_id', $challenge->id)->firstOrFail();
        $this->assertSame(
            json_encode(['challenger' => (int) $this->playerAt($ladder, 8)->id, 'opponent' => (int) $target->id, 'scheduled_at' => $tomorrow->format('Y-m-d H:i:s')]),
            $event->payload
        );

        // challenge_created goes to the opponent, scheduled to both parties.
        Mail::assertSent(PyramidNotificationMail::class, 3);
    }

    #[Test]
    public function player_row_of_a_non_running_ladder_cannot_be_challenged(): void
    {
        $ladder = $this->ladder(10, ['status' => 'setup']);
        $viewer = $this->viewerAt($ladder, 8);
        $target = $this->playerAt($ladder, 5);

        $this->actingAs($viewer)->get('/pyramide/fordern/'.$target->id)
            ->assertRedirect('/pyramide/'.$ladder->id)
            ->assertSessionHas('error', 'Diese Pyramide läuft derzeit nicht.');
    }

    #[Test]
    public function record_is_counted_over_all_matches_not_only_the_listed_ones(): void
    {
        $ladder = $this->ladder();
        $target = $this->playerAt($ladder, 5);

        // 52 wins and 3 losses: the history lists 50, the tiles must still say 52 and 3.
        for ($i = 0; $i < 52; $i++) {
            $this->resolved($ladder, 5, 9, ['resolved' => '2020-01-01 12:00:00']);
        }

        for ($i = 0; $i < 3; $i++) {
            $this->resolved($ladder, 2, 5, ['resolved' => '2020-02-01 12:00:00']);
        }

        $html = $this->get('/pyramide/spieler/'.$target->id)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/Siege<\/p>\s*<p class="ds-kpi__value">52</', $html);
        $this->assertMatchesRegularExpression('/Niederlagen<\/p>\s*<p class="ds-kpi__value">3</', $html);
        $this->assertSame(50, substr_count($html, '(gewonnen)') + substr_count($html, '(verloren)'));
    }
}
