<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Models\User;
use App\Modules\MixedTournament\Models\MixedAvoidPair;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedEventGrant;
use App\Modules\MixedTournament\Models\MixedMember;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Models\MixedRoundPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminEventPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function member(): User
    {
        return User::factory()->status('enabled')->create();
    }

    private function admin(): User
    {
        return User::factory()->status('admin')->create();
    }

    private function grantAdmin(MixedEvent $event, string $role = 'admin'): User
    {
        $user = $this->member();
        MixedEventGrant::factory()->create(['event_id' => $event->id, 'uid' => $user->uid, 'role' => $role]);

        return $user;
    }

    private function url(MixedEvent $event, string $suffix = ''): string
    {
        return '/mixed-tournament/admin/event/'.$event->id.$suffix;
    }

    #[Test]
    public function admin_sees_participants_with_counts(): void
    {
        $event = MixedEvent::factory()->create(['title' => 'Herbst-Beispiel']);
        MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => 'Beispiel Anna', 'gender' => 'f', 'itn' => '4.6', 'age' => 38]);
        MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => 'Spieler 02', 'status' => 'paused']);

        $this->actingAs($this->grantAdmin($event))->get($this->url($event))
            ->assertOk()
            ->assertSee('Herbst-Beispiel')
            ->assertSee('Teilnehmer')
            ->assertSee('(1 aktiv / 2 gesamt)')
            ->assertSee('Beispiel Anna')
            ->assertSee('4,6')
            ->assertSee('ITN + Alter ermitteln')
            ->assertSee('Alle pausieren')
            ->assertSee('Aktivieren')
            ->assertSee('Pausieren')
            ->assertSee(str_replace('/', '\/', route('mixed.admin.member-search', ['event' => $event->id])), false)
            ->assertSee('name="event_id" value="'.$event->id.'"', false)
            ->assertSee('ds-admin-sidebar', false);

        $this->actingAs($this->member())->get($this->url($event))->assertForbidden();
    }

    #[Test]
    public function add_participant_with_member_autofill(): void
    {
        $event = MixedEvent::factory()->create(['event_date' => '2027-04-10']);
        MixedMember::factory()->create(['label' => 'Beispiel Anna', 'gender' => 'f', 'itn' => '5.3', 'birth_date' => '1990-04-11']);

        $this->actingAs($this->grantAdmin($event))->post($this->url($event, '/add-participant'), ['label' => ' Beispiel Anna ', 'gender' => '', 'itn' => '', 'age' => ''])
            ->assertRedirect(route('mixed.admin.event', ['event' => $event->id]))
            ->assertSessionHas('success', 'Teilnehmer hinzugefügt.');

        $participant = MixedParticipant::query()->sole();
        $this->assertSame('Beispiel Anna', $participant->label);
        $this->assertSame('f', $participant->gender);
        $this->assertSame(5.3, (float) $participant->itn);
        $this->assertSame(36, (int) $participant->age);
    }

    #[Test]
    public function add_participant_validation_flashes(): void
    {
        $event = MixedEvent::factory()->create();
        $user = $this->grantAdmin($event);

        $this->actingAs($user)->post($this->url($event, '/add-participant'), ['label' => '  '])
            ->assertRedirect(route('mixed.admin.event', ['event' => $event->id]))
            ->assertSessionHas('error', 'Name ist ein Pflichtfeld.');
        $this->actingAs($user)->post($this->url($event, '/add-participant'), ['label' => 'Spieler 01', 'age' => '121'])
            ->assertSessionHas('error', 'Alter muss zwischen 0 und 120 liegen.');

        $this->assertSame(0, MixedParticipant::query()->count());
    }

    #[Test]
    public function update_requires_matching_event_id(): void
    {
        $event = MixedEvent::factory()->create();
        $other = MixedEvent::factory()->create();
        $foreign = MixedParticipant::factory()->create(['event_id' => $other->id, 'label' => 'Fremd']);
        $own = MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => 'Eigen']);
        $user = $this->grantAdmin($event);

        foreach (['update', 'status', 'remove'] as $action) {
            $this->actingAs($user)->post('/mixed-tournament/admin/participant/'.$foreign->id.'/'.$action, [
                'event_id' => $event->id, 'label' => 'Geändert', 'status' => 'paused',
            ])->assertNotFound();
        }
        // Naming the foreign event instead: the grant does not reach it.
        $this->actingAs($user)->post('/mixed-tournament/admin/participant/'.$foreign->id.'/update', ['event_id' => $other->id, 'label' => 'Geändert'])
            ->assertForbidden();
        // Without event_id.
        $this->actingAs($user)->post('/mixed-tournament/admin/participant/'.$own->id.'/update', ['label' => 'Geändert'])
            ->assertNotFound();

        $this->assertSame('Fremd', $foreign->fresh()->label);
        $this->assertSame('active', $foreign->fresh()->status);

        $this->actingAs($user)->post('/mixed-tournament/admin/participant/'.$own->id.'/update', [
            'event_id' => $event->id, 'label' => 'Eigen Neu', 'gender' => 'm', 'itn' => '6.2', 'age' => '49', 'no_extra_match' => '1',
        ])->assertRedirect(route('mixed.admin.event', ['event' => $event->id]))
            ->assertSessionHas('success', 'Teilnehmer aktualisiert.');

        $own->refresh();
        $this->assertSame(['Eigen Neu', 'm', 49, 1], [$own->label, $own->gender, (int) $own->age, (int) $own->no_extra_match]);
    }

    #[Test]
    public function status_pause_and_activate(): void
    {
        $event = MixedEvent::factory()->create();
        $participant = MixedParticipant::factory()->create(['event_id' => $event->id]);
        $user = $this->grantAdmin($event);
        $url = '/mixed-tournament/admin/participant/'.$participant->id.'/status';

        $this->actingAs($user)->post($url, ['event_id' => $event->id, 'status' => 'paused'])
            ->assertRedirect(route('mixed.admin.event', ['event' => $event->id]));
        $this->assertSame('paused', $participant->fresh()->status);

        $this->actingAs($user)->post($url, ['event_id' => $event->id, 'status' => 'active']);
        $this->assertSame('active', $participant->fresh()->status);

        $this->actingAs($user)->post($url, ['event_id' => $event->id, 'status' => 'deleted'])->assertStatus(422);
        $this->assertSame('active', $participant->fresh()->status);

        MixedParticipant::factory()->count(2)->create(['event_id' => $event->id]);
        $this->actingAs($user)->post($this->url($event, '/pause-all-participants'))
            ->assertRedirect(route('mixed.admin.event', ['event' => $event->id]))
            ->assertSessionHas('success', 'Alle Teilnehmer pausiert.');
        $this->assertSame(0, MixedParticipant::query()->where('status', 'active')->count());
    }

    #[Test]
    public function remove_used_participant_flashes_the_old_text(): void
    {
        $event = MixedEvent::factory()->create();
        $used = MixedParticipant::factory()->create(['event_id' => $event->id]);
        $free = MixedParticipant::factory()->create(['event_id' => $event->id]);
        $round = MixedRound::factory()->create(['event_id' => $event->id]);
        MixedRoundPlayer::query()->create(['round_id' => $round->id, 'participant_id' => $used->id, 'group_no' => 1]);
        $user = $this->grantAdmin($event);

        $this->actingAs($user)->post('/mixed-tournament/admin/participant/'.$used->id.'/remove', ['event_id' => $event->id])
            ->assertRedirect(route('mixed.admin.event', ['event' => $event->id]))
            ->assertSessionHas('error', 'Verwendete Teilnehmer können nicht gelöscht, sondern nur pausiert werden.');
        $this->assertNotNull($used->fresh());

        $this->actingAs($user)->post('/mixed-tournament/admin/participant/'.$free->id.'/remove', ['event_id' => $event->id])
            ->assertSessionHas('success', 'Teilnehmer entfernt.');
        $this->assertNull($free->fresh());
    }

    #[Test]
    public function match_members_fills_empty_fields(): void
    {
        $event = MixedEvent::factory()->create();
        MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => 'Beispiel Anna']);
        MixedMember::factory()->create(['label' => 'Beispiel Anna', 'gender' => 'f', 'itn' => '4.1']);

        $this->actingAs($this->grantAdmin($event))->post($this->url($event, '/match-members'))
            ->assertRedirect(route('mixed.admin.event', ['event' => $event->id]))
            ->assertSessionHas('success', '1 Teilnehmer aus der Mitglieder-Kartei ergänzt.');
    }

    #[Test]
    public function member_search_returns_json_for_event_admins_only(): void
    {
        $event = MixedEvent::factory()->create(['event_date' => '2027-04-10']);
        MixedMember::factory()->create(['label' => 'Beispiel Anna', 'gender' => 'f', 'itn' => '4.6', 'birth_date' => '1989-04-10']);
        MixedMember::factory()->create(['label' => 'Spieler 99']);

        $this->actingAs($this->member())->getJson($this->url($event, '/members?q=Beis'))->assertForbidden();

        $response = $this->actingAs($this->grantAdmin($event))->getJson($this->url($event, '/members?q=Beis'))
            ->assertOk()
            ->assertJsonCount(1, 'members')
            ->assertJsonPath('members.0.label', 'Beispiel Anna')
            ->assertJsonPath('members.0.gender', 'f')
            ->assertJsonPath('members.0.age', 38);

        $first = $response->json('members.0');
        $this->assertArrayNotHasKey('email', $first);
        $this->assertArrayNotHasKey('phone', $first);

        $this->actingAs($this->admin())->getJson($this->url($event, '/members?q=B'))->assertOk()->assertExactJson(['members' => []]);
    }

    #[Test]
    public function member_search_output_is_not_rendered_as_html(): void
    {
        $source = (string) file_get_contents(app_path('Modules/MixedTournament/resources/views/partials/member-picker.blade.php'));

        $this->assertStringNotContainsString('x-html', $source);
        $this->assertStringNotContainsString('{!!', $source);
        $this->assertStringContainsString('x-text', $source);
        $this->assertStringContainsString("route('mixed.admin.member-search'", $source);
    }

    #[Test]
    public function config_hides_grants_and_test_mode_from_event_admins(): void
    {
        $event = MixedEvent::factory()->create();
        $grantAdmin = $this->grantAdmin($event);

        $this->actingAs($grantAdmin)->get($this->url($event, '/config'))
            ->assertOk()
            ->assertSee('Konfiguration')
            ->assertSee('Gegner-Sperren')
            ->assertSee('Testmodus aus – nur Admins können eintragen')
            ->assertDontSee('Berechtigungen')
            ->assertDontSee('Einschalten')
            ->assertDontSee(route('mixed.admin.grant', ['event' => $event->id]));

        $this->actingAs($this->admin())->get($this->url($event, '/config'))
            ->assertOk()
            ->assertSee('Berechtigungen')
            ->assertSee('Einschalten')
            ->assertSee(route('mixed.admin.grant', ['event' => $event->id]));

        // Test mode makes every user event admin, but never a global one.
        $test = MixedEvent::factory()->create(['is_test' => 1]);
        $anyone = $this->member();
        $this->actingAs($anyone)->get($this->url($test, '/config'))->assertOk()->assertDontSee('Ausschalten');
        $this->actingAs($anyone)->post($this->url($test, '/set-test-mode'), ['is_test' => '0'])->assertForbidden();
        $this->actingAs($anyone)->post($this->url($test, '/grant'), ['uid' => $anyone->uid, 'role' => 'admin'])->assertForbidden();
        $this->assertTrue($test->fresh()->is_test);

        $this->actingAs($this->member())->get($this->url($event, '/config'))->assertForbidden();
    }

    #[Test]
    public function grant_requires_existing_user_and_role(): void
    {
        $event = MixedEvent::factory()->create();
        $admin = $this->admin();
        $target = $this->member();
        $config = route('mixed.admin.config', ['event' => $event->id]);

        $this->actingAs($admin)->post($this->url($event, '/grant'), ['uid' => 0, 'role' => 'admin'])
            ->assertRedirect($config)->assertSessionHas('error', 'Bitte Benutzer und Rolle auswählen.');
        $this->actingAs($admin)->post($this->url($event, '/grant'), ['uid' => $target->uid, 'role' => 'owner'])
            ->assertSessionHas('error', 'Bitte Benutzer und Rolle auswählen.');
        $this->assertSame(0, MixedEventGrant::query()->count());

        $this->actingAs($admin)->post($this->url($event, '/grant'), ['uid' => $target->uid, 'role' => 'score'])
            ->assertRedirect($config)->assertSessionHas('success', 'Berechtigung gespeichert.');
        $this->actingAs($admin)->get($config)->assertSee($target->alias.' · Ergebniseingabe');

        // Revoking a uid without a grant in this event is not found; the real one is removed.
        $other = MixedEvent::factory()->create();
        $this->actingAs($admin)->post($this->url($other, '/revoke'), ['uid' => $target->uid])->assertNotFound();
        $this->actingAs($admin)->post($this->url($event, '/revoke'), ['uid' => $target->uid])
            ->assertRedirect($config)->assertSessionHas('success', 'Berechtigung entfernt.');
        $this->assertSame(0, MixedEventGrant::query()->count());
    }

    #[Test]
    public function test_mode_and_court_count_are_saved(): void
    {
        $event = MixedEvent::factory()->create(['court_count' => 4]);
        $config = route('mixed.admin.config', ['event' => $event->id]);

        $this->actingAs($this->admin())->post($this->url($event, '/set-test-mode'), ['is_test' => '1'])
            ->assertRedirect($config)->assertSessionHas('success', 'Testmodus aktiviert.');
        $this->assertTrue($event->fresh()->is_test);
        $this->actingAs($this->admin())->post($this->url($event, '/set-test-mode'), ['is_test' => '0'])
            ->assertSessionHas('success', 'Testmodus deaktiviert.');
        $this->assertFalse($event->fresh()->is_test);

        $this->actingAs($this->grantAdmin($event))->post($this->url($event, '/set-court-count'), ['court_count' => '3'])
            ->assertRedirect($config)->assertSessionHas('success', 'Anzahl der Plätze gespeichert.');
        $this->assertSame(3, $event->fresh()->court_count);

        $this->actingAs($this->grantAdmin($event, 'score'))->post($this->url($event, '/set-court-count'), ['court_count' => '9'])->assertForbidden();
    }

    #[Test]
    public function avoid_pairs_are_scoped_to_the_event(): void
    {
        $event = MixedEvent::factory()->create();
        $other = MixedEvent::factory()->create();
        $a = MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => 'Spieler 01']);
        $b = MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => 'Spieler 02']);
        $foreign = MixedParticipant::factory()->create(['event_id' => $other->id]);
        $foreignPair = MixedAvoidPair::query()->create(['event_id' => $other->id, 'participant_a' => $foreign->id, 'participant_b' => $foreign->id + 100]);
        $user = $this->grantAdmin($event);
        $config = route('mixed.admin.config', ['event' => $event->id]);

        $this->actingAs($user)->post($this->url($event, '/add-avoid-pair'), ['participant_a' => $a->id, 'participant_b' => $a->id])
            ->assertRedirect($config)->assertSessionHas('error', 'Bitte zwei unterschiedliche Spieler auswählen.');
        $this->actingAs($user)->post($this->url($event, '/add-avoid-pair'), ['participant_a' => $a->id, 'participant_b' => $foreign->id])
            ->assertSessionHas('error', 'Bitte zwei unterschiedliche Spieler auswählen.');

        $this->actingAs($user)->post($this->url($event, '/add-avoid-pair'), ['participant_a' => $b->id, 'participant_b' => $a->id])
            ->assertRedirect($config)->assertSessionHas('success', 'Gegner-Sperre hinzugefügt.');
        $pair = MixedAvoidPair::query()->where('event_id', $event->id)->sole();

        $this->actingAs($user)->get($config)
            ->assertSee('Spieler 01')
            ->assertSee('aria-label="Sperre Spieler 01 und Spieler 02 entfernen"', false)
            ->assertDontSee('↔');

        $this->actingAs($user)->post($this->url($event, '/remove-avoid-pair'), ['pair_id' => $foreignPair->id])->assertNotFound();
        $this->assertNotNull(MixedAvoidPair::query()->find($foreignPair->id));

        $this->actingAs($user)->post($this->url($event, '/remove-avoid-pair'), ['pair_id' => $pair->id])
            ->assertRedirect($config)->assertSessionHas('success', 'Gegner-Sperre entfernt.');
        $this->assertSame(0, MixedAvoidPair::query()->where('event_id', $event->id)->count());
    }

    #[Test]
    public function emoji_in_a_label_is_stripped_while_bridged(): void
    {
        $event = MixedEvent::factory()->create();

        $this->actingAs($this->grantAdmin($event))->post($this->url($event, '/add-participant'), ['label' => 'Anna 🎾'])
            ->assertSessionHas('success', 'Teilnehmer hinzugefügt.');

        $this->assertSame('Anna', MixedParticipant::query()->sole()->label);
    }

    #[Test]
    public function blank_scorecard_link_carries_size_and_count(): void
    {
        $event = MixedEvent::factory()->create();

        $this->actingAs($this->grantAdmin($event))->get($this->url($event, '/config'))
            ->assertOk()
            ->assertSee('action="'.route('mixed.scorecard-blank', ['event' => $event->id]).'"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('name="size"', false)
            ->assertSee('<option value="5"', false)
            ->assertSee('name="count"', false)
            ->assertSee('value="8"', false)
            ->assertSee('min="2"', false)
            ->assertSee('max="40"', false)
            ->assertSee('Blanko drucken');
    }

    #[Test]
    public function admin_pages_mark_their_event_entry_active_for_global_admins(): void
    {
        $event = MixedEvent::factory()->create();

        foreach (['' => 'mixed.admin.event', '/config' => 'mixed.admin.config'] as $suffix => $entry) {
            $this->actingAs($this->admin())->get($this->url($event, $suffix))
                ->assertOk()
                ->assertSee('href="'.route($entry, ['event' => $event->id]).'" class="ds-admin-sidebar__link" aria-current="page"', false)
                ->assertSee('href="'.route('mixed.admin.index').'" class="ds-admin-sidebar__link">', false);
        }
    }

    #[Test]
    public function participant_input_is_bounded_by_the_columns(): void
    {
        $event = MixedEvent::factory()->create();
        $user = $this->grantAdmin($event);
        $add = $this->url($event, '/add-participant');

        $this->actingAs($user)->post($add, ['label' => str_repeat('a', 121)])
            ->assertRedirect(route('mixed.admin.event', ['event' => $event->id]))
            ->assertSessionHas('error', 'Der Name darf höchstens 120 Zeichen lang sein.');
        foreach (['150', '-1', '100', 'abc'] as $itn) {
            $this->actingAs($user)->post($add, ['label' => 'Spieler 01', 'itn' => $itn])
                ->assertSessionHas('error', 'ITN muss zwischen 0 und 99,9 liegen.');
        }
        $this->actingAs($user)->post($add, ['label' => ['x']])->assertSessionHas('error', 'Name ist ein Pflichtfeld.');
        $this->actingAs($user)->post($add, ['label' => 'Spieler 01', 'age' => ['3'], 'itn' => ['4']])->assertSessionHas('success', 'Teilnehmer hinzugefügt.');
        $this->actingAs($user)->post($add, ['label' => str_repeat('ä', 120), 'itn' => '4,5'])->assertSessionHas('success', 'Teilnehmer hinzugefügt.');

        $this->assertSame([null, 4.5], MixedParticipant::query()->orderBy('id')->pluck('itn')->map(fn ($v) => $v === null ? null : (float) $v)->all());

        $participant = MixedParticipant::query()->orderBy('id')->firstOrFail();
        $this->actingAs($user)->post('/mixed-tournament/admin/participant/'.$participant->id.'/update', ['event_id' => $event->id, 'label' => 'Neu', 'itn' => '99.95'])
            ->assertSessionHas('error', 'ITN muss zwischen 0 und 99,9 liegen.');
        $this->actingAs($user)->post('/mixed-tournament/admin/participant/'.$participant->id.'/status', ['event_id' => $event->id, 'status' => ['paused']])
            ->assertStatus(422);

        $this->actingAs($user)->get($this->url($event))
            ->assertSee('maxlength="120"', false)
            ->assertSee('max="99.9"', false);
    }

    #[Test]
    public function court_count_is_limited_to_1_to_99(): void
    {
        $event = MixedEvent::factory()->create(['court_count' => 4]);
        $user = $this->grantAdmin($event);

        foreach (['0', '100', '70000', 'x', ''] as $value) {
            $this->actingAs($user)->post($this->url($event, '/set-court-count'), ['court_count' => $value])
                ->assertRedirect(route('mixed.admin.config', ['event' => $event->id]))
                ->assertSessionHas('error', 'Anzahl der Plätze muss zwischen 1 und 99 liegen.');
        }
        $this->actingAs($user)->post($this->url($event, '/set-court-count'), ['court_count' => ['3']])
            ->assertSessionHas('error', 'Anzahl der Plätze muss zwischen 1 und 99 liegen.');
        $this->assertSame(4, $event->fresh()->court_count);

        $this->actingAs($user)->post($this->url($event, '/set-court-count'), ['court_count' => '99'])->assertSessionHas('success');
        $this->assertSame(99, $event->fresh()->court_count);

        $this->actingAs($user)->get($this->url($event, '/config'))->assertSee('max="99"', false);
    }

    #[Test]
    public function participant_routes_authorize_before_looking_up_the_participant(): void
    {
        $event = MixedEvent::factory()->create();
        $participant = MixedParticipant::factory()->create(['event_id' => $event->id]);
        $stranger = $this->member();

        foreach (['update', 'status', 'remove'] as $action) {
            // Existing and non-existing participant ids look the same to a user without rights.
            foreach ([$participant->id, 999999] as $id) {
                $this->actingAs($stranger)->post('/mixed-tournament/admin/participant/'.$id.'/'.$action, [
                    'event_id' => $event->id, 'label' => 'X', 'status' => 'paused',
                ])->assertForbidden();
            }
        }

        // An admin of the event gets 404 for an id that is not in it.
        $this->actingAs($this->grantAdmin($event))->post('/mixed-tournament/admin/participant/999999/status', ['event_id' => $event->id, 'status' => 'paused'])
            ->assertNotFound();
        $this->assertSame('active', $participant->fresh()->status);
    }

    #[Test]
    public function grant_and_avoid_pair_ids_are_read_as_scalars(): void
    {
        $event = MixedEvent::factory()->create();
        $admin = $this->admin();
        $a = MixedParticipant::factory()->create(['event_id' => $event->id]);

        // (int) ['x'] would be 1 = the first user; an array is "no user".
        $this->actingAs($admin)->post($this->url($event, '/grant'), ['uid' => ['x'], 'role' => 'admin'])
            ->assertSessionHas('error', 'Bitte Benutzer und Rolle auswählen.');
        $this->actingAs($admin)->post($this->url($event, '/grant'), ['uid' => (string) $admin->uid, 'role' => ['admin']])
            ->assertSessionHas('error', 'Bitte Benutzer und Rolle auswählen.');
        $this->assertSame(0, MixedEventGrant::query()->count());

        $this->actingAs($admin)->post($this->url($event, '/add-avoid-pair'), ['participant_a' => [$a->id], 'participant_b' => $a->id])
            ->assertSessionHas('error', 'Bitte zwei unterschiedliche Spieler auswählen.');
        $this->actingAs($admin)->post($this->url($event, '/remove-avoid-pair'), ['pair_id' => ['1']])->assertNotFound();
        $this->actingAs($admin)->post($this->url($event, '/set-test-mode'), ['is_test' => ['1']])->assertSessionHas('success', 'Testmodus deaktiviert.');
    }

    #[Test]
    public function test_mode_user_cannot_revoke_and_sees_the_global_only_hint(): void
    {
        $test = MixedEvent::factory()->create(['is_test' => 1]);
        $granted = $this->grantAdmin($test, 'score');
        $anyone = $this->member();

        $this->actingAs($anyone)->post($this->url($test, '/revoke'), ['uid' => $granted->uid])->assertForbidden();
        $this->assertSame(1, MixedEventGrant::query()->count());

        $this->actingAs($anyone)->get($this->url($test, '/config'))
            ->assertSee('Nur globale Mixed-Administratoren dürfen den Testmodus ändern.');
        $this->actingAs($this->admin())->get($this->url($test, '/config'))
            ->assertDontSee('Nur globale Mixed-Administratoren dürfen den Testmodus ändern.');
    }

    #[Test]
    public function placeholder_birth_dates_in_the_roster_leave_the_age_empty(): void
    {
        $event = MixedEvent::factory()->create(['event_date' => '2027-04-10']);
        MixedMember::factory()->create(['label' => 'Beispiel Anna', 'gender' => 'f', 'itn' => '4.6', 'birth_date' => '1900-01-01']);
        MixedMember::factory()->create(['label' => 'Beispiel Bert', 'gender' => 'm', 'itn' => null, 'birth_date' => '1900-01-01']);
        $old = MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => 'Beispiel Bert', 'gender' => 'm']);
        $user = $this->grantAdmin($event);

        $this->actingAs($user)->post($this->url($event, '/add-participant'), ['label' => 'Beispiel Anna'])
            ->assertSessionHas('success', 'Teilnehmer hinzugefügt.');
        $added = MixedParticipant::query()->where('label', 'Beispiel Anna')->sole();
        $this->assertNull($added->age);
        $this->assertSame('f', $added->gender);

        // Nothing usable for Bert (gender set, no ITN, placeholder birth date): not counted as completed.
        $this->actingAs($user)->post($this->url($event, '/match-members'))
            ->assertSessionHas('success', '0 Teilnehmer aus der Mitglieder-Kartei ergänzt.');
        $this->assertNull($old->fresh()->age);

        $this->actingAs($user)->getJson($this->url($event, '/members?q=Beispiel'))
            ->assertJsonPath('members.0.age', null);
    }
}
