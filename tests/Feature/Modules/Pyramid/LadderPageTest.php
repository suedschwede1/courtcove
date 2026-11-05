<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Http\Middleware\StripFourByteCharacters;
use App\Models\User;
use App\Modules\Pyramid\Http\Controllers\LadderController;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidEvent;
use App\Modules\Pyramid\Models\PyramidGrant;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class LadderPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function ladder(int $players = 10): PyramidLadder
    {
        return PyramidLadder::factory()->asDefault()->withRanking($players)->create();
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

    #[Test]
    public function anonymous_visitor_sees_the_default_ladder(): void
    {
        $this->ladder();

        $response = $this->get('/pyramide')
            ->assertOk()
            ->assertSee('Spieler 1')
            ->assertSee('Zum Fordern bitte anmelden.')
            ->assertSee('Rangliste')
            ->assertDontSee('<span>Fordern</span>', false)
            ->assertDontSee('pyramide/fordern/', false)
            ->assertDontSee('Verwaltung')
            ->assertDontSee('Nachmelden')
            ->assertSee('Zum Mitspielen bitte anmelden.');

        $response->assertSee('Laufend')->assertSee('Saison 2027 · 10 Spieler · 0 offene Forderungen');
    }

    #[Test]
    public function ladder_id_route_and_unknown_id(): void
    {
        $default = $this->ladder();
        $other = PyramidLadder::factory()->withRanking(3)->create(['title' => 'Zweite Pyramide', 'season' => 2026]);

        $this->get('/pyramide/'.$other->id)->assertOk()->assertSee('Zweite Pyramide');
        $this->get('/pyramide/'.$default->id)->assertOk()->assertSee('Testpyramide');
        $this->get('/pyramide/999')->assertNotFound();
    }

    #[Test]
    public function no_ladder_is_a_404_with_the_old_text(): void
    {
        $this->get('/pyramide')->assertNotFound();

        // The app's generic 404 page does not print the abort message, so pin it on the controller call.
        try {
            $this->app->make(LadderController::class)->index();
            $this->fail('Expected a 404 exception.');
        } catch (NotFoundHttpException $e) {
            $this->assertSame('Es ist noch keine Pyramide angelegt.', $e->getMessage());
        }
    }

    #[Test]
    public function participant_sees_challengeable_cells_and_self(): void
    {
        $ladder = $this->ladder();
        $viewer = $this->viewerAt($ladder, 8);

        $response = $this->actingAs($viewer)->get('/pyramide')->assertOk();

        $response->assertSee('pyramide/fordern/'.$this->playerAt($ladder, 5)->id, false);
        $response->assertSee('pyramide/fordern/'.$this->playerAt($ladder, 7)->id, false);
        $response->assertDontSee('pyramide/fordern/'.$this->playerAt($ladder, 2)->id, false);
        $response->assertDontSee('pyramide/fordern/'.$this->playerAt($ladder, 8)->id, false);
        $response->assertSee('<span>Fordern</span>', false);
        $response->assertSee('#8 · du', false);
        $response->assertSee('pyramide/spieler/'.$this->playerAt($ladder, 2)->id, false);
        $response->assertSee('Stufe 4');
        $response->assertSee('Du hast derzeit keine offene Forderung.');
        $response->assertDontSee('Nachmelden');
    }

    #[Test]
    public function logged_in_non_participant_can_register_late(): void
    {
        $ladder = $this->ladder();

        $this->actingAs(User::factory()->create())
            ->get('/pyramide')
            ->assertOk()
            ->assertSee('Nachmelden')
            ->assertSee('pyramide/anmelden', false)
            ->assertSee('name="ladderId" value="'.$ladder->id.'"', false)
            ->assertSee('name="itn"', false);
    }

    #[Test]
    public function own_challenge_and_waiting_list_and_feed_are_listed(): void
    {
        $ladder = $this->ladder();
        $viewer = $this->viewerAt($ladder, 8);
        $opponent = $this->playerAt($ladder, 5);
        $challenge = PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id,
            'challenger_id' => $this->playerAt($ladder, 8)->id,
            'opponent_id' => $opponent->id,
            'status' => 'scheduled',
            'scheduled_at' => '2027-03-07 10:00:00',
            'deadline' => '2027-03-18 23:59:59',
        ]);
        PyramidPlayer::factory()->waiting()->create(['ladder_id' => $ladder->id, 'label' => 'Wartender Spieler', 'itn' => '7.4']);
        PyramidEvent::query()->create([
            'ladder_id' => $ladder->id,
            'challenge_id' => $challenge->id,
            'type' => 'challenge',
            'payload' => json_encode(['challenger' => $this->playerAt($ladder, 8)->id, 'opponent' => $opponent->id, 'opponent_pos' => 5]),
            'created' => '2027-03-01 08:12:00',
        ]);
        PyramidEvent::query()->create([
            'ladder_id' => $ladder->id,
            'type' => 'result',
            'payload' => json_encode(['winner' => $opponent->id, 'loser' => $this->playerAt($ladder, 8)->id, 'score' => 'nicht angetreten']),
            'created' => '2027-03-02 09:00:00',
        ]);
        PyramidEvent::query()->create([
            'ladder_id' => $ladder->id,
            'type' => 'admin',
            'payload' => json_encode(['message' => 'Die Saison ist abgeschlossen.']),
            'created' => '2027-03-03 09:00:00',
        ]);

        $response = $this->actingAs($viewer)->get('/pyramide')->assertOk();

        $response->assertSee('gegen Spieler 5 (#5)');
        $response->assertSee('pyramide/forderung/'.$challenge->id, false);
        $response->assertSee('07.03.2027 10:00');
        $response->assertSee('Saison 2027 · 10 Spieler · 1 offene Forderungen');
        $response->assertSee('Wartender Spieler');
        $response->assertSee('Spieler 8 hat Spieler 5 (5) gefordert.');
        $response->assertSee('Spieler 5 gewinnt gegen Spieler 8 nicht angetreten.');
        $response->assertSee('Die Saison ist abgeschlossen.');
        $response->assertSee('01.03.2027 08:12 Uhr');
    }

    #[Test]
    public function grant_holder_sees_the_admin_tab(): void
    {
        $ladder = $this->ladder();
        $holder = User::factory()->create();
        PyramidGrant::query()->create(['ladder_id' => $ladder->id, 'uid' => $holder->uid, 'role' => 'admin', 'created' => '2027-01-01 00:00:00']);

        $this->actingAs($holder)
            ->get('/pyramide')
            ->assertOk()
            ->assertSee('Verwaltung')
            ->assertSee('pyramide/admin/ladder/'.$ladder->id, false);
    }

    #[Test]
    public function status_notices_and_ladder_chips(): void
    {
        $setup = PyramidLadder::factory()->setup()->asDefault()->withRanking(3)->create(['title' => 'Aufbau Pyramide', 'season' => 2026]);
        $closed = PyramidLadder::factory()->closed()->withRanking(3)->create(['title' => 'Alte Pyramide', 'season' => 2025, 'category' => 'Damen']);

        $this->get('/pyramide')
            ->assertOk()
            ->assertSee('Aufbau')
            ->assertSee('Diese Pyramide ist noch im Aufbau — gefordert werden kann noch nicht.')
            ->assertSee('2025 · Damen')
            ->assertSee('pyramide/'.$closed->id, false);

        $this->get('/pyramide/'.$closed->id)
            ->assertOk()
            ->assertSee('Abgeschlossen')
            ->assertSee('Diese Saison ist abgeschlossen.');

        $this->assertNotNull($setup);
    }

    #[Test]
    public function a_single_ladder_shows_no_chips(): void
    {
        $this->ladder();

        $this->get('/pyramide')->assertOk()->assertDontSee('Pyramide wählen');
    }

    #[Test]
    public function championship_months_show_the_notice(): void
    {
        $this->ladder();

        $this->travelTo(now()->setDate(2027, 5, 10));
        $this->get('/pyramide')->assertOk()->assertSee('Meisterschaftsspieler bitte nur nach Absprache fordern (Regel 2.2).');

        $this->travelTo(now()->setDate(2027, 9, 10));
        $this->get('/pyramide')->assertOk()->assertDontSee('Meisterschaftsspieler');
    }

    #[Test]
    public function pyramid_routes_go_through_the_global_stripping_middleware(): void
    {
        $route = Route::getRoutes()->getByName('pyramide.index');
        $this->assertNotNull($route);
        $this->assertContains(StripFourByteCharacters::class, app('router')->gatherRouteMiddleware($route));

        $route = Route::getRoutes()->getByName('pyramide.ladder');
        $this->assertNotNull($route);
        $this->assertContains(StripFourByteCharacters::class, app('router')->gatherRouteMiddleware($route));
    }

    #[Test]
    public function base_path_is_honoured(): void
    {
        $ladder = $this->ladder();
        $viewer = $this->viewerAt($ladder, 8);
        PyramidLadder::factory()->withRanking(2)->create(['season' => 2026]);

        $this->withServerVariables([
            'SCRIPT_NAME' => '/booking/index.php',
            'SCRIPT_FILENAME' => base_path('public/index.php'),
            'PHP_SELF' => '/booking/index.php',
        ]);

        $html = $this->actingAs($viewer)->get('/booking/pyramide')->assertOk()->getContent();

        preg_match_all('#(?:href|action)="([^"]*pyramid[^"]*)"#', (string) $html, $matches);
        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $url) {
            $this->assertMatchesRegularExpression('#^(https?://[^/]+)?/booking/pyramide#', $url, $url);
        }
    }
}
