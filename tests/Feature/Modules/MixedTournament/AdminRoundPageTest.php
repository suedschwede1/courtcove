<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Models\User;
use App\Modules\MixedTournament\Models\MixedAvoidPair;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedEventGrant;
use App\Modules\MixedTournament\Models\MixedMatch;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Models\MixedRoundPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRandomizer;
use Tests\TestCase;

class AdminRoundPageTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsRandomizer;

    private function member(): User
    {
        return User::factory()->status('enabled')->create();
    }

    private function grant(MixedEvent $event, string $role = 'admin'): User
    {
        $user = $this->member();
        MixedEventGrant::factory()->create(['event_id' => $event->id, 'uid' => $user->uid, 'role' => $role]);

        return $user;
    }

    /** @return list<int> participant ids, labels "Spieler A", "Spieler B", ... */
    private function participants(MixedEvent $event, int $count, string $status = 'active'): array
    {
        $ids = [];
        $offset = MixedParticipant::query()->where('event_id', $event->id)->count();
        for ($i = 0; $i < $count; $i++) {
            $ids[] = (int) MixedParticipant::factory()->create([
                'event_id' => $event->id,
                'label' => 'Spieler '.chr(65 + $offset + $i),
                'gender' => $i % 2 === 0 ? 'f' : 'm',
                'status' => $status,
            ])->id;
        }

        return $ids;
    }

    private function round(string $path, MixedRound $round): string
    {
        return '/mixed-tournament/admin/round/'.$round->id.($path !== '' ? '/'.$path : '');
    }

    /** Creates a proposed round with a generated proposal over the given participants. */
    private function proposedRound(MixedEvent $event, User $user, array $ids, string $mode = '4_and_5'): MixedRound
    {
        $this->seedRandomizer();
        $this->actingAs($user)->post('/mixed-tournament/admin/event/'.$event->id.'/create-round', [
            'group_size_mode' => $mode,
            'distribution' => 'random',
            'participants' => $ids,
        ])->assertSessionHas('success');

        return MixedRound::query()->where('event_id', $event->id)->orderByDesc('id')->firstOrFail();
    }

    /** @return array<int, int> participant id => group of a one-row participant */
    private function groupsOf(MixedRound $round): array
    {
        $groups = [];
        foreach (MixedRoundPlayer::query()->where('round_id', $round->id)->orderBy('id')->get() as $row) {
            $groups[(int) $row->participant_id] = $row->group_no === null ? null : (int) $row->group_no;
        }

        return $groups;
    }

    #[Test]
    public function new_round_preselects_active_participants(): void
    {
        $event = MixedEvent::factory()->create(['title' => 'Herbst-Beispiel']);
        [$active] = $this->participants($event, 1);
        [$paused] = $this->participants($event, 1, 'paused');

        $response = $this->actingAs($this->grant($event))->get('/mixed-tournament/admin/event/'.$event->id.'/new-round')
            ->assertOk()
            ->assertSee('ds-admin-sidebar', false)
            ->assertSee('Runde konfigurieren')
            ->assertSee('Gruppengröße')
            ->assertSee('4er + 5er (falls nötig)')
            ->assertSee('Nur 4er, Rest bekommt Zusatzmatch')
            ->assertSee('ITN-Modus – ähnliche Stärke gegeneinander')
            ->assertSee('ITN-ausgeglichen – Stärke über alle Gruppen verteilt')
            ->assertSee('Alters-Modus')
            ->assertSee('Damen möglichst getrennt')
            ->assertSee('Spieler B · pausiert')
            ->assertSee('1 Teilnehmer ausgewählt')
            ->assertSee('Vorschlag generieren')
            ->assertSee('Noch keine Runde angelegt.');

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/value="'.$active.'" class="ds-option__input" checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="'.$paused.'" class="ds-option__input" checked/', $html);
        $this->assertMatchesRegularExpression('/name="group_size_mode" value="4_and_5" class="ds-option__input" checked/', $html);
        $this->assertMatchesRegularExpression('/name="distribution" value="random" class="ds-option__input" checked/', $html);

        // Score users and readers never reach it.
        $this->actingAs($this->grant($event, 'score'))->get('/mixed-tournament/admin/event/'.$event->id.'/new-round')->assertForbidden();
        $this->actingAs($this->member())->get('/mixed-tournament/admin/event/'.$event->id.'/new-round')->assertForbidden();
    }

    #[Test]
    public function create_round_success_redirects_to_the_workspace(): void
    {
        $event = MixedEvent::factory()->create();
        $ids = $this->participants($event, 8);
        $this->seedRandomizer();

        $response = $this->actingAs($this->grant($event))->post('/mixed-tournament/admin/event/'.$event->id.'/create-round', [
            'group_size_mode' => '4_and_5', 'distribution' => 'random', 'participants' => $ids,
        ]);

        $round = MixedRound::query()->sole();
        $response->assertRedirect(route('mixed.admin.round', ['round' => $round->id]))
            ->assertSessionHas('success', 'Runde angelegt und Vorschlag erzeugt.');
        $this->assertSame('proposed', $round->status);
        $this->assertSame(8, MixedRoundPlayer::query()->where('round_id', $round->id)->count());
        $this->assertSame(6, MixedMatch::query()->where('round_id', $round->id)->count());
    }

    #[Test]
    public function create_round_failure_leaves_no_round(): void
    {
        $event = MixedEvent::factory()->create();
        $ids = $this->participants($event, 9);
        $user = $this->grant($event);
        $this->seedRandomizer();

        $this->actingAs($user)->post('/mixed-tournament/admin/event/'.$event->id.'/create-round', [
            'group_size_mode' => '4_only', 'distribution' => 'random', 'participants' => $ids,
        ])->assertRedirect(route('mixed.admin.new-round', ['event' => $event->id]))
            ->assertSessionHas('error', __('mixed::messages.extra_group_impossible'));
        $this->assertSame(0, MixedRound::query()->count());

        // Invalid configuration: its own key, still nothing created.
        $this->actingAs($user)->post('/mixed-tournament/admin/event/'.$event->id.'/create-round', [
            'group_size_mode' => '6_only', 'distribution' => 'random', 'participants' => $ids,
        ])->assertSessionHas('error', 'Ungültige Rundenkonfiguration.');
        $this->actingAs($user)->post('/mixed-tournament/admin/event/'.$event->id.'/create-round', [
            'group_size_mode' => ['4_only'], 'distribution' => 'random', 'participants' => $ids,
        ])->assertSessionHas('error', 'Ungültige Rundenkonfiguration.');
        $this->actingAs($user)->post('/mixed-tournament/admin/event/'.$event->id.'/create-round', [
            'group_size_mode' => '4_and_5', 'distribution' => 'random', 'participants' => array_slice($ids, 0, 3),
        ])->assertSessionHas('error', 'Mindestens 4 Teilnehmer auswählen.');
        $this->assertSame(0, MixedRound::query()->count());
    }

    #[Test]
    public function create_round_refuses_participants_of_another_event(): void
    {
        $event = MixedEvent::factory()->create();
        $other = MixedEvent::factory()->create();
        $ids = $this->participants($event, 7);
        $foreign = $this->participants($other, 1);

        $this->actingAs($this->grant($event))->post('/mixed-tournament/admin/event/'.$event->id.'/create-round', [
            'group_size_mode' => '4_and_5', 'distribution' => 'random', 'participants' => [...$ids, ...$foreign],
        ])->assertNotFound();
        $this->assertSame(0, MixedRound::query()->count());
    }

    #[Test]
    public function mexicano_new_round_is_blocked_while_a_round_is_open(): void
    {
        $event = MixedEvent::factory()->create(['mode' => 'mexicano']);
        $ids = $this->participants($event, 8);
        $user = $this->grant($event);
        $open = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 2, 'status' => 'confirmed']);

        $this->actingAs($user)->get('/mixed-tournament/admin/event/'.$event->id.'/new-round')
            ->assertOk()
            ->assertSee('Runde 2 ist noch nicht abgeschlossen.')
            ->assertSee('Zu Runde 2')
            ->assertSee(route('mixed.admin.round', ['round' => $open->id]))
            ->assertDontSee('Runde erzeugen')
            ->assertDontSee('Gruppengröße');

        $this->actingAs($user)->post('/mixed-tournament/admin/event/'.$event->id.'/create-round', [
            'group_size_mode' => '4_only', 'distribution' => 'random', 'participants' => $ids,
        ])->assertRedirect(route('mixed.admin.new-round', ['event' => $event->id]))
            ->assertSessionHas('error', __('mixed::messages.mexicano_round_open', ['number' => 2]));
        $this->assertSame(1, MixedRound::query()->count());

        $open->update(['status' => 'completed']);
        $this->actingAs($user)->get('/mixed-tournament/admin/event/'.$event->id.'/new-round')
            ->assertOk()
            ->assertSee('Nächste Runde erzeugen')
            ->assertSee('Runde erzeugen')
            ->assertSee('name="group_size_mode" value="4_only"', false)
            ->assertSee('name="distribution" value="random"', false)
            ->assertDontSee('Gruppengröße');
    }

    #[Test]
    public function proposed_workspace_shows_preview_and_swap_picker(): void
    {
        $event = MixedEvent::factory()->create(['court_count' => 1]);
        $ids = $this->participants($event, 8);
        $user = $this->grant($event);
        MixedAvoidPair::query()->create(['event_id' => $event->id, 'participant_a' => $ids[0], 'participant_b' => $ids[1]]);
        $round = $this->proposedRound($event, $user, $ids);

        $this->actingAs($user)->get($this->round('', $round))
            ->assertOk()
            ->assertSee('ds-admin-sidebar', false)
            ->assertSee('Runde 1 – Vorschlag')
            ->assertSee('Runde konfigurieren')
            ->assertSee('Neu generieren')
            ->assertSee('Prüfung des Vorschlags')
            ->assertSee('Matchanzahl:')
            ->assertSee('Wiederholte Begegnungen:')
            ->assertSee('Gegner-Sperren:')
            ->assertSee('1 Platz')
            ->assertSee('2 Wellen')
            ->assertSee('Tippe zwei Spieler aus verschiedenen Gruppen an, um sie zu tauschen.')
            ->assertSee('Tauschen')
            ->assertSee('Abbrechen')
            ->assertSee(route('mixed.admin.swap', ['round' => $round->id]))
            ->assertSee('Runde löschen')
            ->assertSee('Runde wirklich löschen?')
            ->assertSee('Bestätigen')
            ->assertSee('8 Teilnehmer ausgewählt')
            ->assertDontSee('4:0')
            ->assertDontSee(' VS ');

        // A score user sees the groups, but no swap, no configuration, no avoid pairs.
        $this->actingAs($this->grant($event, 'score'))->get($this->round('', $round))
            ->assertOk()
            ->assertSee('Prüfung des Vorschlags')
            ->assertDontSee('Gegner-Sperren:')
            ->assertDontSee(route('mixed.admin.swap', ['round' => $round->id]))
            ->assertDontSee('Neu generieren')
            ->assertDontSee('Runde löschen');

        $this->actingAs($this->member())->get($this->round('', $round))->assertForbidden();
    }

    #[Test]
    public function proposed_workspace_lists_repeats_and_avoid_conflicts(): void
    {
        $event = MixedEvent::factory()->create();
        $p = $this->participants($event, 4);
        $user = $this->grant($event);
        MixedAvoidPair::query()->create(['event_id' => $event->id, 'participant_a' => $p[0], 'participant_b' => $p[2]]);
        foreach ([1, 2] as $no) {
            $done = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => $no, 'status' => 'completed']);
            MixedMatch::factory()->create(['round_id' => $done->id, 'group_no' => 0, 'match_no' => 1, 'team_a_p1' => $p[0], 'team_a_p2' => $p[1], 'team_b_p1' => $p[2], 'team_b_p2' => $p[3], 'games_a' => 2, 'games_b' => 2]);
        }
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 3, 'status' => 'proposed']);
        foreach ($p as $pid) {
            MixedRoundPlayer::query()->create(['round_id' => $round->id, 'participant_id' => $pid, 'group_no' => 0]);
        }
        MixedMatch::factory()->create(['round_id' => $round->id, 'group_no' => 0, 'match_no' => 1, 'team_a_p1' => $p[0], 'team_a_p2' => $p[1], 'team_b_p1' => $p[2], 'team_b_p2' => $p[3]]);

        $this->actingAs($user)->get($this->round('', $round))
            ->assertOk()
            ->assertSee('Spieler A + Spieler B (2× zuvor in derselben Gruppe)')
            ->assertSee('1 Konflikt')
            ->assertSee('Gegner-Sperre nicht vollständig vermeidbar:')
            ->assertSee('Spieler A und Spieler C');
    }

    #[Test]
    public function swap_via_post_regenerates(): void
    {
        $event = MixedEvent::factory()->create();
        $ids = $this->participants($event, 8);
        $user = $this->grant($event);
        $round = $this->proposedRound($event, $user, $ids);
        $groups = $this->groupsOf($round);
        $a = $ids[0];
        $b = collect($ids)->first(fn (int $id): bool => $groups[$id] !== $groups[$a]);

        $this->actingAs($user)->post($this->round('swap', $round), ['participant_a' => $a, 'participant_b' => $b])
            ->assertRedirect(route('mixed.admin.round', ['round' => $round->id]))
            ->assertSessionHas('success', 'Spieler getauscht.');

        $after = $this->groupsOf($round);
        $this->assertSame($groups[$b], $after[$a]);
        $this->assertSame($groups[$a], $after[$b]);
        foreach ([$groups[$a], $groups[$b]] as $groupNo) {
            $members = array_keys(array_filter($after, fn ($g): bool => $g === $groupNo));
            foreach (MixedMatch::query()->where('round_id', $round->id)->where('group_no', $groupNo)->get() as $match) {
                foreach (['team_a_p1', 'team_a_p2', 'team_b_p1', 'team_b_p2'] as $slot) {
                    $this->assertContains((int) $match->{$slot}, $members);
                }
            }
        }

        // Same player: the rule flash; a participant of another event: not found.
        $this->actingAs($user)->post($this->round('swap', $round), ['participant_a' => $a, 'participant_b' => $a])
            ->assertSessionHas('error', 'Bitte zwei unterschiedliche Spieler auswählen.');
        $foreign = $this->participants(MixedEvent::factory()->create(), 1)[0];
        $this->actingAs($user)->post($this->round('swap', $round), ['participant_a' => $a, 'participant_b' => $foreign])->assertNotFound();
        $this->actingAs($user)->post($this->round('swap', $round), ['participant_a' => $a, 'participant_b' => [$b]])->assertNotFound();
        $this->assertSame($after, $this->groupsOf($round));

        // Score users may not swap.
        $this->actingAs($this->grant($event, 'score'))->post($this->round('swap', $round), ['participant_a' => $a, 'participant_b' => $b])->assertForbidden();
    }

    #[Test]
    public function filler_chips_are_disabled_and_the_filler_swap_is_refused(): void
    {
        $event = MixedEvent::factory()->create();
        $ids = $this->participants($event, 10);
        $user = $this->grant($event);
        $round = $this->proposedRound($event, $user, $ids, '4_only');
        $rows = MixedRoundPlayer::query()->where('round_id', $round->id)->get()->countBy('participant_id');
        $filler = (int) $rows->filter(fn (int $n): bool => $n === 2)->keys()->first();
        $single = (int) $rows->filter(fn (int $n): bool => $n === 1)->keys()->first();
        $this->assertGreaterThan(0, $filler);

        $html = $this->actingAs($user)->get($this->round('', $round))->assertOk()
            ->assertSee('Spieler mit 2× füllen die Zusatzgruppe auf und können nicht getauscht werden.')
            ->assertSee('Zusatzgruppe')
            ->getContent();
        $this->assertDoesNotMatchRegularExpression('/data-id="'.$filler.'"/', $html);
        $this->assertMatchesRegularExpression('/data-id="'.$single.'"/', $html);

        $this->actingAs($user)->post($this->round('swap', $round), ['participant_a' => $filler, 'participant_b' => $single])
            ->assertSessionHas('error', 'Auffüller aus Zusatzgruppen können nicht eindeutig getauscht werden.');
        $this->assertSame(2, MixedRoundPlayer::query()->where('round_id', $round->id)->where('participant_id', $filler)->count());
    }

    #[Test]
    public function swap_on_a_mexicano_round_flashes_and_changes_nothing(): void
    {
        $event = MixedEvent::factory()->create(['mode' => 'mexicano']);
        $ids = $this->participants($event, 8);
        $user = $this->grant($event);
        $round = $this->proposedRound($event, $user, $ids, '4_only');
        $groups = $this->groupsOf($round);
        $matches = MixedMatch::query()->where('round_id', $round->id)->orderBy('id')->get()->toArray();
        $b = collect($ids)->first(fn (int $id): bool => $groups[$id] !== $groups[$ids[0]]);

        $this->actingAs($user)->post($this->round('swap', $round), ['participant_a' => $ids[0], 'participant_b' => $b])
            ->assertRedirect(route('mixed.admin.round', ['round' => $round->id]))
            ->assertSessionHas('error', 'Im Mexicano-Modus wird nur mit einem Aussetzer getauscht.');

        $this->assertSame($groups, $this->groupsOf($round));
        $this->assertSame($matches, MixedMatch::query()->where('round_id', $round->id)->orderBy('id')->get()->toArray());

        // The workspace offers no player swap at all.
        $this->actingAs($user)->get($this->round('', $round))
            ->assertOk()
            ->assertDontSee(route('mixed.admin.swap', ['round' => $round->id]))
            ->assertDontSee('Tippe zwei Spieler');
    }

    #[Test]
    public function swap_sitter_flow(): void
    {
        $event = MixedEvent::factory()->create(['mode' => 'mexicano']);
        $ids = $this->participants($event, 9);
        $user = $this->grant($event);
        $round = $this->proposedRound($event, $user, $ids, '4_only');
        $groups = $this->groupsOf($round);
        $sitter = (int) array_search(null, $groups, true);
        $player = (int) collect($groups)->filter(fn ($g): bool => $g !== null)->keys()->first();

        $this->actingAs($user)->get($this->round('', $round))
            ->assertOk()
            ->assertSee('Setzen diese Runde aus:')
            ->assertSee('Aussetzer tauschen')
            ->assertSee('name="sitter_id"', false)
            ->assertSee('name="player_id"', false);

        $this->actingAs($user)->post($this->round('swap-sitter', $round), ['sitter_id' => $sitter, 'player_id' => $player])
            ->assertRedirect(route('mixed.admin.round', ['round' => $round->id]))
            ->assertSessionHas('success', 'Aussetzer getauscht.');
        $after = $this->groupsOf($round);
        $this->assertSame($groups[$player], $after[$sitter]);
        $this->assertNull($after[$player]);

        // Two players (no sitter): refused with the rule text; a foreign id is not found.
        $other = (int) collect($after)->filter(fn ($g): bool => $g !== null)->keys()->last();
        $this->actingAs($user)->post($this->round('swap-sitter', $round), ['sitter_id' => $other, 'player_id' => $sitter])
            ->assertSessionHas('error', 'Aussetzer konnte nicht getauscht werden.');
        $foreign = $this->participants(MixedEvent::factory()->create(), 1)[0];
        $this->actingAs($user)->post($this->round('swap-sitter', $round), ['sitter_id' => $player, 'player_id' => $foreign])->assertNotFound();
        $this->assertSame($after, $this->groupsOf($round));
    }

    #[Test]
    public function generate_keeps_participants_and_stores_a_changed_config(): void
    {
        $event = MixedEvent::factory()->create();
        $ids = $this->participants($event, 9);
        $user = $this->grant($event);
        $round = $this->proposedRound($event, $user, array_slice($ids, 0, 8));

        $this->actingAs($user)->post($this->round('generate', $round), ['group_size_mode' => '4_and_5', 'distribution' => 'itn', 'participants' => array_slice($ids, 0, 8)])
            ->assertSessionHas('success', 'Vorschlag erzeugt.');
        $this->assertSame('itn', $round->fresh()->distribution);
        $this->assertNotContains($ids[8], MixedRoundPlayer::query()->where('round_id', $round->id)->pluck('participant_id')->map(fn ($id): int => (int) $id)->all());

        // A refused proposal keeps the previous configuration as well (one transaction).
        $this->actingAs($user)->post($this->round('generate', $round), ['group_size_mode' => '4_only', 'distribution' => 'age', 'participants' => $ids])
            ->assertSessionHas('error', __('mixed::messages.extra_group_impossible'));
        $fresh = $round->fresh();
        $this->assertSame('4_and_5', $fresh->group_size_mode);
        $this->assertSame('itn', $fresh->distribution);
        $this->assertSame(8, MixedRoundPlayer::query()->where('round_id', $round->id)->count());

        $this->actingAs($user)->post($this->round('update-config', $round), ['group_size_mode' => 'x', 'distribution' => 'random'])
            ->assertSessionHas('error', 'Ungültige Rundenkonfiguration.');
    }

    #[Test]
    public function confirm_complete_reopen_delete_flow(): void
    {
        $event = MixedEvent::factory()->create();
        $ids = $this->participants($event, 8);
        $user = $this->grant($event);
        $round = $this->proposedRound($event, $user, $ids);

        $this->actingAs($user)->post($this->round('confirm', $round))
            ->assertRedirect(route('mixed.admin.round', ['round' => $round->id]))
            ->assertSessionHas('success', 'Runde bestätigt. Ergebnisse können jetzt eingetragen werden.');
        $this->assertSame('confirmed', $round->fresh()->status);

        // Confirmed: no delete, no second confirm (wrong state).
        $this->actingAs($user)->post($this->round('delete', $round))
            ->assertRedirect(route('mixed.admin.round', ['round' => $round->id]))
            ->assertSessionHas('error', 'Bestätigte Runden können nicht gelöscht werden.');
        $this->actingAs($user)->post($this->round('confirm', $round))
            ->assertSessionHas('error', 'Die Runde ist dafür nicht im passenden Zustand.');

        $this->actingAs($user)->get($this->round('', $round))
            ->assertOk()
            ->assertSee('Runde 1 – bestätigt')
            ->assertSee('Runde abschließen')
            ->assertSee('Runde abschließen? Ergebnisse sind danach nicht mehr korrigierbar.')
            ->assertSee('Scorecard drucken')
            ->assertSee('Zur Runden-Übersicht')
            ->assertSee('data-scroll-restore', false);

        $this->actingAs($user)->post($this->round('complete', $round))
            ->assertSessionHas('error', 'Alle Ergebnisse müssen vor dem Abschluss eingetragen sein.');
        MixedMatch::query()->where('round_id', $round->id)->update(['games_a' => 3, 'games_b' => 1]);
        $this->actingAs($user)->post($this->round('complete', $round))
            ->assertSessionHas('success', 'Runde abgeschlossen. Ergebnisse sind jetzt gesperrt.');
        $this->assertSame('completed', $round->fresh()->status);

        $this->actingAs($user)->get($this->round('', $round))
            ->assertOk()
            ->assertSee('Runde wieder öffnen')
            ->assertDontSee('data-scroll-restore', false)
            ->assertDontSee('name="result"', false);

        $this->actingAs($user)->post($this->round('reopen', $round))
            ->assertSessionHas('success', 'Runde wieder geöffnet. Ergebnisse sind wieder korrigierbar.');
        $this->assertSame('confirmed', $round->fresh()->status);

        $second = $this->proposedRound($event, $user, $ids);
        $this->actingAs($user)->post($this->round('delete', $second))
            ->assertRedirect(route('mixed.admin.event', ['event' => $event->id]))
            ->assertSessionHas('success', 'Runde gelöscht.');
        $this->assertNull($second->fresh());
        $this->assertSame(0, MixedRoundPlayer::query()->where('round_id', $second->id)->count());
        $this->assertSame(0, MixedMatch::query()->where('round_id', $second->id)->count());
    }

    #[Test]
    public function score_user_can_score_but_not_confirm(): void
    {
        $event = MixedEvent::factory()->create();
        $ids = $this->participants($event, 8);
        $admin = $this->grant($event);
        $scorer = $this->grant($event, 'score');
        $round = $this->proposedRound($event, $admin, $ids);

        foreach (['confirm', 'delete', 'generate', 'update-config', 'swap-sitter'] as $action) {
            $this->actingAs($scorer)->post($this->round($action, $round), ['participants' => $ids])->assertForbidden();
        }
        $this->actingAs($admin)->post($this->round('confirm', $round));
        $match = MixedMatch::query()->where('round_id', $round->id)->orderBy('id')->firstOrFail();

        $this->actingAs($scorer)->post($this->round('score', $round), ['match_id' => $match->id, 'result' => '3:1'])
            ->assertRedirect(route('mixed.admin.round', ['round' => $round->id]))
            ->assertSessionHas('success', 'Ergebnis gespeichert.');
        $this->assertSame([3, 1], [(int) $match->fresh()->games_a, (int) $match->fresh()->games_b]);

        foreach (['complete', 'reopen'] as $action) {
            $this->actingAs($scorer)->post($this->round($action, $round))->assertForbidden();
        }
        $this->actingAs($scorer)->get($this->round('', $round))
            ->assertOk()
            ->assertSee('name="result" value="0:4"', false)
            ->assertDontSee('Runde abschließen')
            ->assertSee(route('mixed.standings', ['event' => $event->id]))
            ->assertDontSee(route('mixed.admin.standings', ['event' => $event->id]));

        // Readers cannot score.
        $this->actingAs($this->member())->post($this->round('score', $round), ['match_id' => $match->id, 'result' => '4:0'])->assertForbidden();
    }

    #[Test]
    public function score_buttons_only_while_confirmed(): void
    {
        $event = MixedEvent::factory()->create();
        $ids = $this->participants($event, 8);
        $user = $this->grant($event);
        $round = $this->proposedRound($event, $user, $ids);

        $this->actingAs($user)->get($this->round('', $round))->assertOk()->assertDontSee('name="result"', false);

        $this->actingAs($user)->post($this->round('confirm', $round));
        $html = $this->actingAs($user)->get($this->round('', $round))->assertOk()->getContent();
        $this->assertSame(6 * 5, substr_count($html, 'name="result"'));
        $this->assertSame(6 * 5, substr_count($html, 'data-scroll-save>'));
        foreach (['4:0', '3:1', '2:2', '1:3', '0:4'] as $result) {
            $this->assertStringContainsString('name="result" value="'.$result.'"', $html);
        }

        $match = MixedMatch::query()->where('round_id', $round->id)->orderBy('id')->firstOrFail();
        $this->actingAs($user)->post($this->round('score', $round), ['match_id' => $match->id, 'result' => '2:2']);
        $this->actingAs($user)->get($this->round('', $round))->assertSee('aria-pressed="true"', false);

        // Proposed rounds take no score.
        $proposed = $this->proposedRound($event, $user, $ids);
        $other = MixedMatch::query()->where('round_id', $proposed->id)->orderBy('id')->firstOrFail();
        $this->actingAs($user)->post($this->round('score', $proposed), ['match_id' => $other->id, 'result' => '4:0'])
            ->assertSessionHas('error', 'Die Runde ist dafür nicht im passenden Zustand.');
        $this->assertNull($other->fresh()->games_a);
    }

    #[Test]
    public function invalid_result_flashes(): void
    {
        $event = MixedEvent::factory()->create();
        $ids = $this->participants($event, 8);
        $user = $this->grant($event);
        $round = $this->proposedRound($event, $user, $ids);
        $this->actingAs($user)->post($this->round('confirm', $round));
        $match = MixedMatch::query()->where('round_id', $round->id)->orderBy('id')->firstOrFail();

        foreach (['5:0', '2:1', '', ['3:1']] as $result) {
            $this->actingAs($user)->post($this->round('score', $round), ['match_id' => $match->id, 'result' => $result])
                ->assertRedirect(route('mixed.admin.round', ['round' => $round->id]))
                ->assertSessionHas('error', 'Ungültiges Ergebnis.');
        }
        $this->assertNull($match->fresh()->games_a);

        // A match of another round (other event) is not found, nothing changes there.
        $otherEvent = MixedEvent::factory()->create();
        $otherRound = MixedRound::factory()->create(['event_id' => $otherEvent->id, 'status' => 'confirmed']);
        $p = $this->participants($otherEvent, 4);
        $foreign = MixedMatch::factory()->create(['round_id' => $otherRound->id, 'group_no' => 0, 'match_no' => 1, 'team_a_p1' => $p[0], 'team_a_p2' => $p[1], 'team_b_p1' => $p[2], 'team_b_p2' => $p[3]]);
        $this->actingAs($user)->post($this->round('score', $round), ['match_id' => $foreign->id, 'result' => '4:0'])->assertNotFound();
        $this->assertNull($foreign->fresh()->games_a);
    }

    #[Test]
    public function rounds_of_another_event_need_that_events_rights(): void
    {
        $event = MixedEvent::factory()->create();
        $other = MixedEvent::factory()->create();
        $round = MixedRound::factory()->create(['event_id' => $other->id, 'status' => 'proposed']);
        $user = $this->grant($event);

        $this->actingAs($user)->get($this->round('', $round))->assertForbidden();
        $this->actingAs($user)->post($this->round('confirm', $round))->assertForbidden();
        $this->assertSame('proposed', $round->fresh()->status);
        $this->actingAs($user)->get('/mixed-tournament/admin/round/999999')->assertRedirect(route('mixed.index'));
    }

    #[Test]
    public function mexicano_workspace_shows_courts_and_lifecycle_texts(): void
    {
        config(['tournament-base.courts' => [1 => 'Alpha', 2 => 'Beta']]);
        $event = MixedEvent::factory()->create(['mode' => 'mexicano', 'court_count' => 2]);
        $ids = $this->participants($event, 8);
        $user = $this->grant($event);
        $round = $this->proposedRound($event, $user, $ids, '4_only');

        $this->actingAs($user)->get($this->round('', $round))
            ->assertOk()
            ->assertSee('Runde 1 – Vorschlag')
            ->assertSee('Alpha')
            ->assertSee('noch offen')
            ->assertSee('Bestätigen')
            ->assertDontSee('name="result"', false);

        $this->actingAs($user)->post($this->round('confirm', $round));
        $this->actingAs($user)->get($this->round('', $round))
            ->assertSee('Runde abschließen? Erst danach lässt sich die nächste Runde erzeugen.')
            ->assertSee('Alle Ergebnisse eintragen, dann abschließen')
            ->assertSee('name="result" value="4:0"', false);

        MixedMatch::query()->where('round_id', $round->id)->update(['games_a' => 4, 'games_b' => 0]);
        $this->actingAs($user)->post($this->round('complete', $round))->assertSessionHas('success');
        $this->actingAs($user)->get($this->round('', $round))
            ->assertSee('Runde wieder öffnen? Bereits erzeugte Folgerunden beruhen auf dem bisherigen Ergebnis')
            ->assertSee(route('mixed.admin.standings', ['event' => $event->id]));
    }

    #[Test]
    public function admin_durchmischung_and_standings_use_admin_tabs(): void
    {
        $event = MixedEvent::factory()->create();
        $this->participants($event, 4);
        $admin = $this->grant($event);

        foreach (['durchmischung', 'standings'] as $page) {
            $this->actingAs($admin)->get('/mixed-tournament/admin/event/'.$event->id.'/'.$page)
                ->assertOk()
                ->assertSee('ds-admin-sidebar', false)
                ->assertSee('Konfiguration')
                ->assertSee('+ Neue Runde')
                ->assertSee(route('mixed.admin.config', ['event' => $event->id]))
                ->assertSee(route('mixed.admin.'.$page, ['event' => $event->id]));

            $this->actingAs($this->grant($event, 'score'))->get('/mixed-tournament/admin/event/'.$event->id.'/'.$page)->assertForbidden();
        }

        // A scorer's admin tabs lead to the player boards, which he can open.
        $scorer = $this->grant($event, 'score');
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'status' => 'confirmed']);
        $this->actingAs($scorer)->get($this->round('', $round))
            ->assertOk()
            ->assertSee(route('mixed.durchmischung', ['event' => $event->id]))
            ->assertDontSee(route('mixed.admin.durchmischung', ['event' => $event->id]))
            ->assertDontSee('Konfiguration');
    }

    #[Test]
    public function actions_need_post(): void
    {
        $event = MixedEvent::factory()->create();
        $round = MixedRound::factory()->create(['event_id' => $event->id]);
        $user = $this->grant($event);

        foreach (['generate', 'swap', 'swap-sitter', 'confirm', 'complete', 'reopen', 'delete', 'score', 'update-config'] as $action) {
            $this->actingAs($user)->get($this->round($action, $round))->assertStatus(405);
        }
    }
}
