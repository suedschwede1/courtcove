<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\TournamentBase\Support\TestModeSession;
use App\Services\UiLinter;
use App\Services\UiRules;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Draw\Concerns\BuildsTokenDraw;
use Tests\TestCase;

class TokenPageTest extends TestCase
{
    use BuildsTokenDraw;
    use LazilyRefreshDatabase;

    private function page(User $user, ?Draw $draw = null): string
    {
        return $this->actingAs($user)->get(route('draw.tokens', ['did' => ($draw ?? $this->draw)->did]))
            ->assertOk()->getContent();
    }

    private function simulate(User $admin, int $tpid): void
    {
        $this->actingAs($admin);
        $this->app->make(TestModeSession::class)->simulate($admin, 'player', $tpid);
    }

    #[Test]
    public function eligible_player_sees_selectable_tokens(): void
    {
        $this->tokenDraw();
        $html = $this->page($this->users[3]);

        $this->assertSame(4, substr_count($html, '>wählen<'));
        $this->assertStringContainsString('4 von 4 frei', $html);
        $this->assertStringContainsString('Du hast noch keinen Token gewählt.', $html);
        $this->assertStringContainsString('Demo-Meisterschaft - Gruppenauslosung', $html);
        $this->assertStringContainsString(route('draw.record', ['slug' => $this->draw->slug]), $html);
        $this->assertStringContainsString(route('draw.tokens.pick', ['did' => $this->draw->did]), $html);
        $this->assertStringContainsString('Das ist endgültig und kann nicht geändert werden.', $html);
        $this->assertStringContainsString('Ja, endgültig wählen', $html);
        // Free tokens never show their slot: the list would be a menu.
        $this->assertStringNotContainsString('Gruppe A2', $html);
        $this->assertStringNotContainsString('Gruppe B2', $html);
    }

    #[Test]
    public function deadline_is_shown_while_picking(): void
    {
        $this->tokenDraw();
        Draw::query()->whereKey($this->draw->did)->update(['pick_deadline' => '2027-03-10 20:00:00']);

        $this->assertStringContainsString('Zeit bis 10.03.2027 20:00.', $this->page($this->users[3]));
    }

    #[Test]
    public function blocked_tokens_are_hidden_with_count(): void
    {
        $this->tokenDraw();
        $this->partners(3, 2); // partner is the head of group B
        $b2 = $this->tokenWithSlot('B2');
        $b3 = $this->tokenWithSlot('B3');

        $html = $this->page($this->users[3]);

        $this->assertStringNotContainsString('>'.$b2->token.'<', $html);
        $this->assertStringNotContainsString('>'.$b3->token.'<', $html);
        $this->assertStringContainsString('>'.$this->tokenWithSlot('A2')->token.'<', $html);
        $this->assertSame(2, substr_count($html, '>wählen<'));
        $this->assertStringContainsString('2 für dich wählbar', $html);
        $this->assertStringNotContainsString('von 4 frei', $html);

        // Another player sees every token.
        $this->assertStringContainsString('4 von 4 frei', $this->page($this->users[4]));
    }

    #[Test]
    public function own_token_is_highlighted_after_pick(): void
    {
        $this->tokenDraw();
        $token = $this->tokenWithSlot('B2');
        $this->claimDirectly($token, 3);
        DrawToken::query()->whereKey($token->dtid)->update(['claimed' => '2027-03-01 18:30:00']);
        $this->claimDirectly($this->tokenWithSlot('A3'), 5);
        DrawToken::query()->whereKey($this->tokenWithSlot('A3')->dtid)->update(['assigned_by' => 'fallback']);

        $html = $this->page($this->users[3]);

        $this->assertStringContainsString('Dein Token', $html);
        $this->assertStringContainsString('ds-card--highlight', $html);
        $this->assertStringContainsString('Du bist in Gruppe B2', $html);
        $this->assertStringContainsString('gewählt am 01.03.2027 18:30 · eine Änderung ist nicht möglich', $html);
        $this->assertStringNotContainsString('>wählen<', $html);
        $this->assertStringContainsString('2 von 4 frei', $html);
        // Claimed tokens name the player and the group; fallback ones are marked.
        $this->assertStringContainsString('Demo Spieler 05', $html);
        $this->assertStringContainsString('zugeteilt', $html);
        $this->assertStringContainsString('Gruppe A3', $html);
    }

    #[Test]
    public function non_eligible_participant_sees_the_lostopf_text(): void
    {
        $this->tokenDraw();
        $html = $this->page($this->users[1]); // seeded head, not in the pot

        $this->assertStringContainsString('Du bist bei dieser Auslosung nicht im Lostopf. Gesetzte Gruppenköpfe stehen bereits fest, und der andere Bewerb wird getrennt gelost.', $html);
        $this->assertStringNotContainsString('>wählen<', $html);

        $stranger = User::factory()->create(['status' => 'enabled']);
        $this->assertStringContainsString('Du bist nicht als Teilnehmer gemeldet und wählst deshalb nicht mit.', $this->page($stranger));
    }

    #[Test]
    public function admin_sees_admin_hint(): void
    {
        $this->tokenDraw();
        $admin = User::factory()->create(['status' => 'admin']);

        // ZF2 showed the admin tabs on the token page; the draw tab links to the draws admin.
        $html = $this->page($admin);
        $this->assertStringContainsString('href="'.route('draw.index').'"', $html);
        $this->assertStringContainsString('Du bist nicht als Teilnehmer gemeldet', $html);

        $this->assertStringNotContainsString('href="'.route('draw.index').'"', $this->page($this->users[3]));
    }

    #[Test]
    public function closed_draw_shows_no_pick_buttons(): void
    {
        $this->tokenDraw();
        Draw::query()->whereKey($this->draw->did)->update(['status' => 'closed']);

        $html = $this->page($this->users[3]);
        $this->assertStringNotContainsString('>wählen<', $html);
        $this->assertStringNotContainsString('Du hast noch keinen Token gewählt.', $html);
    }

    #[Test]
    public function simulated_player_in_test_tournament_can_pick(): void
    {
        $this->tokenDraw(['is_test' => 1]);
        $admin = User::factory()->create(['status' => 'admin']);
        $this->simulate($admin, $this->tpid[4]);

        $html = $this->get(route('draw.tokens', ['did' => $this->draw->did]))->assertOk()->getContent();

        $this->assertStringContainsString('Testturnier:', $html);
        $this->assertStringContainsString('Du wählst als Demo Spieler 04.', $html);
        $this->assertSame(4, substr_count($html, '>wählen<'));
    }

    #[Test]
    public function simulated_player_in_real_tournament_cannot_pick(): void
    {
        $this->tokenDraw();
        $admin = User::factory()->create(['status' => 'admin']);
        // The admin is in the pot himself: the simulation must still lock the pick.
        TournamentPlayer::query()->whereKey($this->tpid[6])->update(['uid' => $admin->uid]);
        $this->simulate($admin, $this->tpid[4]);

        $html = $this->get(route('draw.tokens', ['did' => $this->draw->did]))->assertOk()->getContent();

        $this->assertStringNotContainsString('>wählen<', $html);
        $this->assertStringNotContainsString('Du wählst als', $html);
        $this->assertStringContainsString('Im Testmodus ist die Tokenwahl gesperrt. Beende ihn zuerst.', $html);
    }

    #[Test]
    public function unknown_or_malformed_draw_ids_are_not_found(): void
    {
        $this->tokenDraw();
        $user = $this->users[3];

        $this->actingAs($user)->get('/draw/tokens/999999')->assertNotFound();
        $this->actingAs($user)->get('/draw/tokens/99999999999999999999')->assertNotFound();
        $this->actingAs($user)->post('/draw/tokens/999999/pick', ['dtid' => '1'])->assertNotFound();
        $this->actingAs($user)->get('/draw/tokens/abc')->assertNotFound();
    }

    #[Test]
    public function draw_without_tid_uses_the_current_tournament(): void
    {
        $this->tokenDraw([], withTid: false);

        $this->assertSame(4, substr_count($this->page($this->users[3]), '>wählen<'));
    }

    #[Test]
    public function guests_are_sent_to_the_login(): void
    {
        $this->tokenDraw();

        $this->get(route('draw.tokens', ['did' => $this->draw->did]))->assertRedirect();
        $this->assertGuest();
        $this->get('/draw/tokens/999999')->assertRedirect(); // login before the id is resolved
        $this->post(route('draw.tokens.pick', ['did' => $this->draw->did]), ['dtid' => '1'])->assertRedirect();
        $this->assertNull(DrawToken::query()->where('did', $this->draw->did)->whereNotNull('claimed_tpid')->first());
    }

    #[Test]
    public function page_passes_the_ui_linter(): void
    {
        $this->assertSame([], (new UiLinter(app_path('Modules/Draw/resources/views'), UiRules::all()))->run());
    }
}
