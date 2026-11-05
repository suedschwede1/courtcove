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

class AdminChallengeActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PyramidLadder $ladder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ladder = PyramidLadder::factory()->asDefault()->withRanking(10)->create();
    }

    private function admin(): User
    {
        return User::factory()->status('admin')->create();
    }

    private function playerAt(int $position, ?PyramidLadder $ladder = null): PyramidPlayer
    {
        return PyramidPlayer::query()->where('ladder_id', ($ladder ?? $this->ladder)->id)->where('position', $position)->firstOrFail();
    }

    private function mailable(int $position, ?string $email = null): void
    {
        $user = User::factory()->create(['email' => $email ?? 'spieler'.$position.'@example.test']);
        $this->playerAt($position)->update(['uid' => $user->uid]);
    }

    private function challenge(string $status = 'open', array $attributes = [], ?PyramidLadder $ladder = null): PyramidChallenge
    {
        $ladder ??= $this->ladder;
        $challenger = $this->playerAt(8, $ladder);
        $opponent = $this->playerAt(5, $ladder);

        return PyramidChallenge::factory()->create($attributes + [
            'ladder_id' => $ladder->id,
            'challenger_id' => $challenger->id,
            'opponent_id' => $opponent->id,
            'challenger_pos' => 8,
            'opponent_pos' => 5,
            'status' => $status,
        ]);
    }

    private function overrideUrl(PyramidChallenge $challenge): string
    {
        return '/pyramide/admin/challenge/'.$challenge->id.'/override';
    }

    private function ladderUrl(?PyramidLadder $ladder = null, string $action = ''): string
    {
        return '/pyramide/admin/ladder/'.($ladder ?? $this->ladder)->id.($action === '' ? '' : '/'.$action);
    }

    private function positions(): array
    {
        return PyramidPlayer::query()->where('ladder_id', $this->ladder->id)->orderBy('id')->pluck('position', 'id')->map(fn ($p) => $p === null ? null : (int) $p)->all();
    }

    #[Test]
    public function extend_respects_the_limit(): void
    {
        Mail::fake();
        $this->mailable(8);
        $this->mailable(5);
        $deadline = now()->subDays(2);
        $challenge = $this->challenge('open', ['deadline' => $deadline->format('Y-m-d')]);

        // Old deadline 00:00 + 28 days against the new date at 23:59: the latest accepted day is old + 27.
        $this->actingAs($this->admin())->post($this->overrideUrl($challenge), [
            'decision' => 'extend', 'new_deadline' => $deadline->copy()->addDays(28)->format('Y-m-d'), 'admin_note' => 'Urlaub',
        ])->assertRedirect($this->ladderUrl())
            ->assertSessionHas('error', 'Die neue Frist darf höchstens 28 Tage über der alten liegen.');
        $this->assertSame($deadline->format('Y-m-d'), substr((string) $challenge->fresh()->deadline, 0, 10));
        Mail::assertNothingSent();

        $latest = $deadline->copy()->addDays(27)->format('Y-m-d');
        $this->actingAs($this->admin())->post($this->overrideUrl($challenge), [
            'decision' => 'extend', 'new_deadline' => $latest, 'admin_note' => '  Urlaub  ',
        ])->assertRedirect($this->ladderUrl())
            ->assertSessionHas('success', 'Frist verlängert bis '.$latest.'.');

        $this->assertSame($latest, substr((string) $challenge->fresh()->deadline, 0, 10));
        $this->assertSame('open', $challenge->fresh()->status);
        Mail::assertSent(PyramidNotificationMail::class, 2);
        $event = PyramidEvent::query()->where('challenge_id', $challenge->id)->where('type', 'admin')->latest('id')->firstOrFail();
        $this->assertSame('Frist verlängert bis '.$latest.': Urlaub', json_decode((string) $event->payload, true)['message']);
    }

    #[Test]
    public function the_note_is_mandatory_and_an_unknown_decision_is_a_422(): void
    {
        Mail::fake();
        $challenge = $this->challenge('open', ['deadline' => now()->subDay()->format('Y-m-d')]);

        $this->actingAs($this->admin())->post($this->overrideUrl($challenge), ['decision' => 'expired', 'admin_note' => '  '])
            ->assertRedirect($this->ladderUrl())
            ->assertSessionHas('error', 'Bitte eine Begründung angeben.');
        $this->assertSame('open', $challenge->fresh()->status);

        $this->actingAs($this->admin())->post($this->overrideUrl($challenge), ['decision' => 'banana', 'admin_note' => 'x'])
            ->assertStatus(422);
        $this->actingAs($this->admin())->post($this->overrideUrl($challenge), ['admin_note' => 'x'])
            ->assertStatus(422);
        $this->assertSame('open', $challenge->fresh()->status);
        Mail::assertNothingSent();
    }

    #[Test]
    public function forfeit_opponent_moves_the_challenger_up(): void
    {
        $recipients = [];
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$recipients): void {
            $recipients[] = $event->message->getTo()[0]->getAddress();
        });
        $this->mailable(8);
        $this->mailable(5);
        $challenge = $this->challenge('open', ['deadline' => now()->subDay()->format('Y-m-d')]);
        $challengerId = (int) $challenge->challenger_id;

        $this->actingAs($this->admin())->post($this->overrideUrl($challenge), ['decision' => 'forfeit_opponent', 'admin_note' => 'Gegner nicht erschienen'])
            ->assertRedirect($this->ladderUrl())
            ->assertSessionHas('success', 'Forderung als nicht angetreten gewertet.');

        $fresh = $challenge->fresh();
        $this->assertSame('forfeited', $fresh->status);
        $this->assertSame($challengerId, (int) $fresh->winner_id);
        $this->assertSame(5, (int) PyramidPlayer::query()->findOrFail($challengerId)->position);
        $this->assertSame(6, (int) PyramidPlayer::query()->findOrFail($challenge->opponent_id)->position);
        $this->assertEqualsCanonicalizing(['spieler8@example.test', 'spieler5@example.test'], $recipients);
    }

    #[Test]
    public function forfeit_challenger_leaves_the_ranking_alone(): void
    {
        Mail::fake();
        $challenge = $this->challenge('open', ['deadline' => now()->subDay()->format('Y-m-d')]);
        $before = $this->positions();

        $this->actingAs($this->admin())->post($this->overrideUrl($challenge), ['decision' => 'forfeit_challenger', 'admin_note' => 'Fordernder nicht erschienen'])
            ->assertSessionHas('success', 'Forderung als nicht angetreten gewertet.');

        $this->assertSame('forfeited', $challenge->fresh()->status);
        $this->assertSame((int) $challenge->opponent_id, (int) $challenge->fresh()->winner_id);
        $this->assertSame($before, $this->positions());
    }

    #[Test]
    public function expire_moves_nobody(): void
    {
        Mail::fake();
        $this->mailable(8);
        $this->mailable(5);
        $challenge = $this->challenge('open', ['deadline' => now()->subDay()->format('Y-m-d')]);
        $before = $this->positions();

        $this->actingAs($this->admin())->post($this->overrideUrl($challenge), ['decision' => 'expired', 'admin_note' => 'Verletzung'])
            ->assertRedirect($this->ladderUrl())
            ->assertSessionHas('success', 'Forderung als verfallen eingetragen.');

        $fresh = $challenge->fresh();
        $this->assertSame('expired', $fresh->status);
        $this->assertSame('Verletzung', $fresh->admin_note);
        $this->assertNotNull($fresh->resolved);
        $this->assertSame($before, $this->positions());
        Mail::assertSent(PyramidNotificationMail::class, 2);
    }

    #[Test]
    public function an_already_resolved_challenge_is_reported_and_nobody_is_mailed(): void
    {
        Mail::fake();
        $this->mailable(8);
        $challenge = $this->challenge('played', ['winner_id' => $this->playerAt(8)->id, 'score' => '6:3 6:3', 'resolved' => '2020-01-05 12:00:00']);

        $this->actingAs($this->admin())->post($this->overrideUrl($challenge), ['decision' => 'expired', 'admin_note' => 'zu spät'])
            ->assertRedirect($this->ladderUrl())
            ->assertSessionHas('error', 'Diese Forderung ist bereits abgeschlossen.');

        $this->assertSame('played', $challenge->fresh()->status);
        Mail::assertNothingSent();
    }

    #[Test]
    public function correction_mails_both_and_keeps_positions(): void
    {
        Mail::fake();
        $this->mailable(8);
        $this->mailable(5);
        $challenge = $this->challenge('played', ['winner_id' => $this->playerAt(8)->id, 'score' => '6:3 6:3', 'resolved' => '2020-01-05 12:00:00']);
        $before = $this->positions();

        $this->actingAs($this->admin())->post('/pyramide/admin/challenge/'.$challenge->id.'/correct-result', [
            'winner_id' => $this->playerAt(5)->id, 'score' => '6:4 7:5', 'admin_note' => 'Vertippt',
        ])->assertRedirect($this->ladderUrl())
            ->assertSessionHas('success', 'Ergebnis richtiggestellt. Die Rangliste wurde NICHT verändert — Positionen bitte bei Bedarf von Hand korrigieren.');

        $fresh = $challenge->fresh();
        $this->assertSame((int) $this->playerAt(5)->id, (int) $fresh->winner_id);
        $this->assertSame('6:4 7:5', $fresh->score);
        $this->assertSame('Vertippt', $fresh->admin_note);
        $this->assertSame($before, $this->positions());
        Mail::assertSent(PyramidNotificationMail::class, 2);
    }

    #[Test]
    public function correction_rejects_a_stranger_as_winner_an_open_challenge_and_a_blank_note(): void
    {
        Mail::fake();
        $played = $this->challenge('played', ['winner_id' => $this->playerAt(8)->id, 'score' => '6:3 6:3', 'resolved' => '2020-01-05 12:00:00']);
        $open = $this->challenge('open');
        $url = fn (PyramidChallenge $c): string => '/pyramide/admin/challenge/'.$c->id.'/correct-result';

        $this->actingAs($this->admin())->post($url($played), ['winner_id' => $this->playerAt(2)->id, 'score' => '6:0 6:0', 'admin_note' => 'x'])
            ->assertStatus(422);
        $this->actingAs($this->admin())->post($url($played), ['winner_id' => $this->playerAt(5)->id, 'score' => '6:0 6:0', 'admin_note' => ' '])
            ->assertSessionHas('error', 'Bitte eine Begründung angeben.');
        $this->actingAs($this->admin())->post($url($open), ['winner_id' => $this->playerAt(5)->id, 'score' => '6:0 6:0', 'admin_note' => 'x'])
            ->assertSessionHas('error', 'Nur ein verbuchtes Ergebnis lässt sich richtigstellen.');

        $this->assertSame('6:3 6:3', $played->fresh()->score);
        Mail::assertNothingSent();
    }

    #[Test]
    public function config_validates_days(): void
    {
        $this->actingAs($this->admin())->post($this->ladderUrl(null, 'config'), [
            'title' => 'Neu', 'days_to_play' => 366, 'winner_protected_days' => 7, 'loser_cooldown_days' => 3,
        ])->assertRedirect($this->ladderUrl())
            ->assertSessionHas('error', 'Fristen müssen zwischen 0 und 365 Tagen liegen.');
        $this->assertNotSame('Neu', $this->ladder->fresh()->title);

        $other = PyramidLadder::factory()->create(['is_default' => 1]);
        $this->ladder->update(['is_default' => 0]);

        $this->actingAs($this->admin())->post($this->ladderUrl(null, 'config'), [
            'title' => ' Neu ', 'category' => '', 'days_to_play' => 14, 'winner_protected_days' => 7, 'loser_cooldown_days' => 3, 'is_default' => '1',
        ])->assertRedirect($this->ladderUrl())
            ->assertSessionHas('success', 'Einstellungen gespeichert.');

        $fresh = $this->ladder->fresh();
        $this->assertSame('Neu', $fresh->title);
        $this->assertNull($fresh->category);
        $this->assertSame([14, 7, 3, 1], [(int) $fresh->days_to_play, (int) $fresh->winner_protected_days, (int) $fresh->loser_cooldown_days, (int) $fresh->is_default]);
        $this->assertSame(0, (int) $other->fresh()->is_default);
    }

    #[Test]
    public function close_marks_the_ladder_and_a_closed_ladder_blocks_results(): void
    {
        $user = User::factory()->create(['email' => 'spieler8@example.test']);
        $this->playerAt(8)->update(['uid' => $user->uid]);
        $challenge = $this->challenge('scheduled', ['scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s')]);

        $this->actingAs($this->admin())->post($this->ladderUrl(null, 'close'))
            ->assertRedirect($this->ladderUrl())
            ->assertSessionHas('success', 'Saison abgeschlossen.');
        $this->assertSame('closed', $this->ladder->fresh()->status);
        $this->assertTrue(PyramidEvent::query()->where('ladder_id', $this->ladder->id)->where('type', 'admin')->exists());

        $this->actingAs($user)->post('/pyramide/forderung/'.$challenge->id.'/ergebnis', ['s1a' => 6, 's1b' => 4, 's2a' => 6, 's2b' => 3])
            ->assertSessionHas('error', 'Diese Pyramide ist abgeschlossen und kann nicht mehr bespielt werden.');
        $this->assertSame('scheduled', $challenge->fresh()->status);
    }

    #[Test]
    public function grant_rejects_an_unknown_user_number(): void
    {
        $this->actingAs($this->admin())->post($this->ladderUrl(null, 'grant'), ['uid' => '987654'])
            ->assertSessionHas('error', 'Mitglied nicht gefunden.');
        $this->actingAs($this->admin())->post($this->ladderUrl(null, 'grant'), ['uid' => '-5'])
            ->assertSessionHas('error', 'Bitte eine Benutzernummer angeben.');

        $this->assertSame(0, PyramidGrant::query()->where('ladder_id', $this->ladder->id)->count());
    }

    #[Test]
    public function a_second_close_adds_no_second_event_and_says_so(): void
    {
        $url = $this->ladderUrl(null, 'close');
        $this->actingAs($this->admin())->post($url)->assertSessionHas('success', 'Saison abgeschlossen.');
        $this->actingAs($this->admin())->post($url)->assertSessionHas('info', 'Die Saison war bereits abgeschlossen.');

        $this->assertSame(1, PyramidEvent::query()->where('ladder_id', $this->ladder->id)->where('type', 'admin')->count());
    }

    #[Test]
    public function config_rejects_a_blank_title_but_keeps_the_old_one_when_the_key_is_absent(): void
    {
        $old = $this->ladder->title;
        $days = ['days_to_play' => 14, 'winner_protected_days' => 7, 'loser_cooldown_days' => 3];

        $this->actingAs($this->admin())->post($this->ladderUrl(null, 'config'), ['title' => '  '] + $days)
            ->assertSessionHas('error', 'Bitte einen Titel angeben.');
        $this->assertSame($old, $this->ladder->fresh()->title);

        $this->actingAs($this->admin())->post($this->ladderUrl(null, 'config'), $days)
            ->assertSessionHas('success', 'Einstellungen gespeichert.');
        $this->assertSame($old, $this->ladder->fresh()->title);
    }

    #[Test]
    public function correction_rejects_overlong_score_and_note_with_a_message(): void
    {
        $played = $this->challenge('played', ['winner_id' => $this->playerAt(8)->id, 'score' => '6:3 6:3', 'resolved' => '2020-01-05 12:00:00']);
        $url = '/pyramide/admin/challenge/'.$played->id.'/correct-result';

        $this->actingAs($this->admin())->post($url, ['winner_id' => $this->playerAt(5)->id, 'score' => str_repeat('6', 41), 'admin_note' => 'x'])
            ->assertSessionHas('error', 'Das Ergebnis darf höchstens 40 Zeichen lang sein.');
        $this->actingAs($this->admin())->post($url, ['winner_id' => $this->playerAt(5)->id, 'score' => '6:0 6:0', 'admin_note' => str_repeat('n', 161)])
            ->assertSessionHas('error', 'Die Begründung darf höchstens 160 Zeichen lang sein.');

        $this->assertSame('6:3 6:3', $played->fresh()->score);
    }

    #[Test]
    public function grant_then_revoke(): void
    {
        $holder = User::factory()->create();
        $reader = User::factory()->create();

        $this->actingAs($holder)->get($this->ladderUrl())->assertForbidden();

        $this->actingAs($this->admin())->post($this->ladderUrl(null, 'grant'), ['uid' => '0'])
            ->assertSessionHas('error', 'Bitte eine Benutzernummer angeben.');
        $this->actingAs($this->admin())->post($this->ladderUrl(null, 'grant'), ['uid' => (string) $holder->uid])
            ->assertRedirect($this->ladderUrl())
            ->assertSessionHas('success', 'Verwaltungsrecht vergeben.');
        $this->actingAs($this->admin())->post($this->ladderUrl(null, 'grant'), ['uid' => (string) $holder->uid]);

        $this->assertSame(1, PyramidGrant::query()->where('ladder_id', $this->ladder->id)->where('uid', $holder->uid)->count());
        $this->actingAs($holder)->get($this->ladderUrl())->assertOk();

        $this->actingAs($this->admin())->post($this->ladderUrl(null, 'revoke'), ['uid' => (string) $holder->uid])
            ->assertRedirect($this->ladderUrl())
            ->assertSessionHas('success', 'Verwaltungsrecht entzogen.');
        $this->actingAs($holder)->get($this->ladderUrl())->assertForbidden();

        // Revoking somebody without a grant is the same quiet success as in ZF2.
        $this->actingAs($this->admin())->post($this->ladderUrl(null, 'revoke'), ['uid' => (string) $reader->uid])
            ->assertSessionHas('success', 'Verwaltungsrecht entzogen.');
    }

    #[Test]
    public function a_grant_holder_acts_only_on_the_granted_ladder_and_never_on_a_foreign_challenge(): void
    {
        $holder = User::factory()->create();
        PyramidGrant::query()->create(['ladder_id' => $this->ladder->id, 'uid' => $holder->uid, 'role' => 'admin', 'created' => '2027-01-01 00:00:00']);
        $foreign = PyramidLadder::factory()->withRanking(10)->create();
        $foreignChallenge = $this->challenge('open', ['deadline' => now()->subDay()->format('Y-m-d')], $foreign);
        $own = $this->challenge('open', ['deadline' => now()->subDay()->format('Y-m-d')]);

        $this->actingAs($holder)->post($this->overrideUrl($own), ['decision' => 'expired', 'admin_note' => 'ok'])
            ->assertSessionHas('success');
        $this->assertSame('expired', $own->fresh()->status);

        $this->actingAs($holder)->post($this->overrideUrl($foreignChallenge), ['decision' => 'expired', 'admin_note' => 'ok'])->assertForbidden();
        $this->actingAs($holder)->post($this->ladderUrl($foreign, 'config'), ['days_to_play' => 1, 'winner_protected_days' => 1, 'loser_cooldown_days' => 1])->assertForbidden();
        $this->actingAs($holder)->post($this->ladderUrl($foreign, 'close'))->assertForbidden();
        $this->actingAs($holder)->post($this->ladderUrl($foreign, 'grant'), ['uid' => (string) $holder->uid])->assertForbidden();
        $this->actingAs($holder)->post($this->ladderUrl($foreign, 'revoke'), ['uid' => (string) $holder->uid])->assertForbidden();
        $this->assertSame('open', $foreignChallenge->fresh()->status);
        $this->assertSame('running', $foreign->fresh()->status);
        $this->assertSame(0, PyramidGrant::query()->where('ladder_id', $foreign->id)->count());

        $this->actingAs($holder)->post('/pyramide/admin/challenge/9999/override', ['decision' => 'expired', 'admin_note' => 'x'])->assertNotFound();
        $this->actingAs($holder)->post('/pyramide/admin/challenge/9999/correct-result', [])->assertNotFound();
    }
}
