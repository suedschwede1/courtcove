<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Modules\TournamentBase\Support\TestModeSession;
use App\Modules\TournamentBase\Support\WhatsApp;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

/**
 * Seed 1 is played by the viewing participant, seed 4 by another member with e-mail and phone, the other
 * seeds are guests without an account (uid null), seed 2 has a phone number.
 */
class PlayersPageTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    private User $participant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->participant = $this->member(['email' => 'teilnehmer01@example.test']);
        $other = $this->member(['email' => 'spieler04@example.test']);
        $this->championship([1 => $this->participant, 4 => $other]);
        $this->tournament->players()->where('seed', 4)->update(['phone' => '0664 1234567']);
        $this->tournament->players()->where('seed', 2)->update(['phone' => '+43 699 7654321']);
        $this->app->forgetScopedInstances();
    }

    private function assertNoContacts(string $html): void
    {
        $this->assertStringNotContainsString('tel:', $html);
        $this->assertStringNotContainsString('mailto:', $html);
        $this->assertStringNotContainsString('wa.me', $html);
        $this->assertStringNotContainsString('0664 1234567', $html);
        $this->assertStringNotContainsString('spieler04@example.test', $html);
        $this->assertStringContainsString('Kontaktdaten sind nur für Turnierteilnehmer sichtbar.', $html);
    }

    #[Test]
    public function member_sees_no_contacts(): void
    {
        $html = $this->actingAs($this->member())->get('/tournament/players')->assertOk()->getContent();

        $this->assertNoContacts($html);
        $this->assertStringContainsString('Setzliste', $html);
        for ($seed = 1; $seed <= 6; $seed++) {
            $this->assertStringContainsString(sprintf('Demo Spieler %02d', $seed), $html);
        }
        // members see no ITN of other players
        $this->assertStringNotContainsString('ITN 4,', $html);
    }

    #[Test]
    public function participant_sees_contacts_of_all_players(): void
    {
        $html = $this->actingAs($this->participant)->get('/tournament/players')->assertOk()->getContent();

        $this->assertStringContainsString('href="tel:06641234567"', $html);
        $this->assertStringContainsString('href="mailto:spieler04@example.test"', $html);
        $this->assertStringContainsString('href="tel:+436997654321"', $html);
        $this->assertStringNotContainsString('Kontaktdaten sind nur für Turnierteilnehmer sichtbar.', $html);
        $this->assertStringNotContainsString('ITN 4,', $html);
    }

    #[Test]
    public function admin_simulating_a_member_sees_no_contacts(): void
    {
        $admin = $this->admin();
        $adminHtml = $this->actingAs($admin)->get('/tournament/players')->getContent();
        $this->assertStringContainsString('mailto:spieler04@example.test', $adminHtml);
        $this->assertStringContainsString('ITN 4,4', $adminHtml);

        $this->app->make(TestModeSession::class)->simulate($admin, 'member');

        $html = $this->actingAs($admin)->get('/tournament/players')->assertOk()->getContent();

        $this->assertNoContacts($html);
        $this->assertStringNotContainsString('ITN 4,', $html);
    }

    #[Test]
    public function whatsapp_link_uses_the_shared_helper(): void
    {
        $html = $this->actingAs($this->participant)->get('/tournament/players')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.e(WhatsApp::url('0664 1234567')).'"', $html);
        $this->assertStringContainsString('href="'.e(WhatsApp::url('+43 699 7654321')).'"', $html);
        $this->assertSame(2, substr_count($html, 'https://wa.me/'));
    }

    #[Test]
    public function guest_players_without_uid_have_no_email(): void
    {
        $html = $this->actingAs($this->participant)->get('/tournament/players')->assertOk()->getContent();

        // two players with an account (the participant himself and seed 4): address link and button each
        $this->assertSame(4, substr_count($html, 'href="mailto:'));
        $this->assertStringContainsString('href="mailto:teilnehmer01@example.test"', $html);
        // 4 guests without e-mail plus 4 players without phone
        $this->assertSame(8, substr_count($html, 'nicht hinterlegt'));
    }

    #[Test]
    public function search_and_group_filter_are_alpine_driven(): void
    {
        $html = $this->actingAs($this->member())->get('/tournament/players')->assertOk()->getContent();

        $this->assertStringContainsString('x-data=', $html);
        $this->assertStringContainsString('x-model="term"', $html);
        $this->assertStringContainsString('data-name="demo spieler 01"', $html);
        $this->assertStringContainsString('data-group="A"', $html);
        $this->assertStringContainsString('Gruppe B', $html);
        $this->assertStringContainsString('Kein Teilnehmer passt zu dieser Suche.', $html);
        $this->assertStringNotContainsString('<script', (string) file_get_contents(app_path('Modules/Tournament/resources/views/players.blade.php')));
    }
}
