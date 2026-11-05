<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Models\User;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function member(): User
    {
        return User::factory()->status('enabled')->create();
    }

    #[Test]
    public function index_redirects_to_the_default_event(): void
    {
        MixedEvent::factory()->create();
        $default = MixedEvent::factory()->create(['is_default' => 1]);

        $this->actingAs($this->member())->get('/mixed-tournament')
            ->assertRedirect(route('mixed.event', ['event' => $default->id]));
    }

    #[Test]
    public function index_lists_events_without_default(): void
    {
        $first = MixedEvent::factory()->create(['title' => 'Herbst-Beispiel']);
        MixedEvent::factory()->create(['title' => 'Winter-Beispiel', 'mode' => 'mexicano']);
        MixedParticipant::factory()->count(3)->create(['event_id' => $first->id]);
        MixedRound::factory()->create(['event_id' => $first->id, 'round_no' => 1]);
        MixedRound::factory()->create(['event_id' => $first->id, 'round_no' => 2]);

        $this->actingAs($this->member())->get('/mixed-tournament')
            ->assertOk()
            ->assertSee('Herbst-Beispiel')
            ->assertSee('Winter-Beispiel')
            ->assertSee('3 Teilnehmer')
            ->assertSee('2 Runden')
            ->assertSee('0 Runden')
            ->assertSee('Mexicano')
            ->assertSee(route('mixed.event', ['event' => $first->id]));
    }

    #[Test]
    public function info_is_translated(): void
    {
        $user = $this->member();

        $this->actingAs($user)->get('/mixed-tournament/info')
            ->assertOk()
            ->assertSee('Wie funktioniert das Mixed-/Fun-Turnier?')
            ->assertSee('Restspieler und die Zusatzgruppe')
            ->assertDontSee('Gegner-Sperren</h2>', false);

        $this->actingAs($user)->withUnencryptedCookie('locale', 'en')->get('/mixed-tournament/info')
            ->assertOk()
            ->assertSee('How does the mixed/fun tournament work?')
            ->assertDontSee('Wie funktioniert das Mixed-/Fun-Turnier?');
    }

    #[Test]
    public function info_shows_the_avoid_pair_section_to_global_admins_only(): void
    {
        $admin = User::factory()->status('admin')->create();

        $this->actingAs($admin)->get('/mixed-tournament/info')
            ->assertOk()
            ->assertSee('Gegner-Sperren')
            ->assertSee(route('mixed.admin.index'));
    }

    #[Test]
    public function every_info_and_common_key_exists_in_both_locales(): void
    {
        foreach (['common', 'info'] as $file) {
            $de = Lang::get('mixed::'.$file, [], 'de');
            $en = Lang::get('mixed::'.$file, [], 'en');
            $this->assertIsArray($de);
            $this->assertIsArray($en);
            $this->assertSame(array_keys($this->flatten($de)), array_keys($this->flatten($en)), $file);
        }
    }

    #[Test]
    public function event_page_shows_participants_and_tabs(): void
    {
        $event = MixedEvent::factory()->create(['title' => 'Herbst-Beispiel', 'event_date' => '2026-11-07']);
        MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => 'Beispiel Anna', 'gender' => 'f', 'itn' => '4.10']);
        MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => 'Beispiel Bernd', 'gender' => 'm', 'status' => 'paused']);
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 1]);

        $response = $this->actingAs($this->member())->get('/mixed-tournament/event/'.$event->id)->assertOk();

        $response->assertSee('Herbst-Beispiel')
            ->assertSee('Sa, 7. November · 2 Teilnehmer · 1 Runde · Americano')
            ->assertSee('Beispiel Anna')
            ->assertSee('ITN 4,1')
            ->assertSee('pausiert')
            ->assertSee('(1 aktiv / 2 gesamt)')
            ->assertSeeInOrder(['Teilnehmer', 'Runde 1', 'Durchmischung', 'Wertung'])
            ->assertSee(route('mixed.round', ['round' => $round->id]))
            ->assertSee(route('mixed.admin.index'))
            ->assertDontSee('Konfiguration')
            ->assertDontSee('+ Neue Runde')
            ->assertDontSee('Zur Verwaltung dieses Events');
    }

    #[Test]
    public function event_page_shows_admin_tabs_and_link_to_event_admins(): void
    {
        $event = MixedEvent::factory()->create();
        $admin = User::factory()->status('admin')->create();

        $this->actingAs($admin)->get('/mixed-tournament/event/'.$event->id)
            ->assertOk()
            ->assertSee('Konfiguration')
            ->assertSee('+ Neue Runde')
            ->assertSee(route('mixed.admin.event', ['event' => $event->id]));
    }

    #[Test]
    public function completed_round_tab_carries_a_check_icon_not_a_character(): void
    {
        $event = MixedEvent::factory()->create();
        MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 1, 'status' => 'completed']);
        MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 2, 'status' => 'confirmed']);

        $html = $this->actingAs($this->member())->get('/mixed-tournament/event/'.$event->id)->assertOk()->getContent();

        $this->assertStringNotContainsString('✓', $html);
        $this->assertMatchesRegularExpression('#Runde 1<svg[^>]*class="ds-icon"[^>]*>\s*<path d="M5 12l5 5 9-10"/>#', $html);
        $this->assertDoesNotMatchRegularExpression('#Runde 2<svg#', $html);
        $this->assertStringContainsString('(abgeschlossen)', $html);
    }

    #[Test]
    public function mexicano_event_has_one_rounds_tab_to_the_open_round(): void
    {
        $event = MixedEvent::factory()->create(['mode' => 'mexicano']);
        MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 1, 'status' => 'completed']);
        $open = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 2, 'status' => 'confirmed']);

        $html = $this->actingAs($this->member())->get('/mixed-tournament/event/'.$event->id)->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<nav class="ds-tabs[^"]*"[^>]*>(.*?)</nav>#s', $html, $nav));
        $this->assertStringContainsString('href="'.route('mixed.round', ['round' => $open->id]).'" class="ds-tabs__tab">Runden</a>', $nav[1]);
        $this->assertStringNotContainsString('Runde 1', $nav[1]);
        $this->assertStringNotContainsString('Runde 2', $nav[1]);
    }

    #[Test]
    public function test_event_shows_the_testmodus_badge(): void
    {
        $test = MixedEvent::factory()->create(['is_test' => 1]);
        $normal = MixedEvent::factory()->create();
        $user = $this->member();

        $this->actingAs($user)->get('/mixed-tournament/event/'.$test->id)->assertOk()->assertSee('Testmodus');
        $this->actingAs($user)->get('/mixed-tournament/event/'.$normal->id)->assertOk()->assertDontSee('Testmodus');
    }

    #[Test]
    public function no_view_toggle_and_no_inline_styles(): void
    {
        $event = MixedEvent::factory()->create(['is_default' => 1]);
        MixedParticipant::factory()->create(['event_id' => $event->id]);
        MixedRound::factory()->create(['event_id' => $event->id]);
        $user = $this->member();

        foreach (['/mixed-tournament/event/'.$event->id, '/mixed-tournament/info'] as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
            $main = substr($html, (int) strpos($html, '<main'));
            $this->assertStringNotContainsString('mixedTournamentView', $html, $url);
            $this->assertStringNotContainsString('data-mx-view', $html, $url);
            $this->assertStringNotContainsString(' style="', $main, $url);
        }
    }

    #[Test]
    public function html_escapes_labels(): void
    {
        $event = MixedEvent::factory()->create(['title' => '<i>T</i>']);
        MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => '<b>x</b>']);

        $this->actingAs($this->member())->get('/mixed-tournament/event/'.$event->id)
            ->assertOk()
            ->assertSee('&lt;b&gt;x&lt;/b&gt;', false)
            ->assertDontSee('<b>x</b>', false)
            ->assertDontSee('<i>T</i>', false);
    }

    #[Test]
    public function guests_are_sent_to_the_login(): void
    {
        $event = MixedEvent::factory()->create();

        $this->get('/mixed-tournament/event/'.$event->id)->assertRedirect();
        $this->assertGuest();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $flat += $this->flatten($value, $prefix.$key.'.');
            } else {
                $flat[$prefix.$key] = $value;
            }
        }
        ksort($flat);

        return $flat;
    }
}
