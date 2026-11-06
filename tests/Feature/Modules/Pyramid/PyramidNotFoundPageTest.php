<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Models\PyramidGrant;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Support\PyramidNotFound;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * An unknown ladder (and a missing player or challenge) shows a friendly page of the module, still with status 404.
 */
class PyramidNotFoundPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // These tests pin the public behaviour (config off); TournamentLoginRequiredTest covers the default.
        config(['tournament-base.require_login' => false]);
    }

    private function admin(): User
    {
        return User::factory()->status('admin')->create();
    }

    #[Test]
    public function an_unknown_ladder_is_a_friendly_404_in_the_public_layout(): void
    {
        PyramidLadder::factory()->asDefault()->withRanking(3)->create();

        $response = $this->get('/pyramide/999');

        $response->assertNotFound()
            ->assertSee('Nicht gefunden')
            ->assertSee('Diese Pyramide gibt es nicht.')
            ->assertSee('Zur Pyramide')
            ->assertSee('href="'.route('pyramide.index').'"', false)
            ->assertSee('<aside id="admin-nav"', false);
        $this->assertInstanceOf(PyramidNotFound::class, $response->exception);
    }

    #[Test]
    public function without_any_ladder_the_way_back_leads_to_the_calendar(): void
    {
        $this->get('/pyramide')
            ->assertNotFound()
            ->assertSee('Es ist noch keine Pyramide angelegt.')
            ->assertSee('href="'.route('calendar.index').'"', false)
            ->assertDontSee('Zur Pyramide');
    }

    #[Test]
    public function the_page_follows_the_language(): void
    {
        PyramidLadder::factory()->asDefault()->withRanking(3)->create();

        $this->withUnencryptedCookie('locale', 'en')
            ->get('/pyramide/999')
            ->assertNotFound()
            ->assertSee('Not found')
            ->assertSee('This ladder does not exist.')
            ->assertSee('To the ladder');
    }

    #[Test]
    public function missing_players_and_challenges_use_the_same_page(): void
    {
        PyramidLadder::factory()->asDefault()->withRanking(3)->create();

        $this->get('/pyramide/spieler/9999')->assertNotFound()->assertSee('Diesen Teilnehmer gibt es nicht.')->assertSee('Nicht gefunden');
        $this->get('/pyramide/forderung/9999')->assertNotFound()->assertSee('Diese Forderung gibt es nicht.')->assertSee('Nicht gefunden');
    }

    #[Test]
    public function the_administration_gets_the_page_inside_the_admin_shell(): void
    {
        $this->actingAs($this->admin())
            ->get('/pyramide/admin/ladder/9999')
            ->assertNotFound()
            ->assertSee('Diese Pyramide gibt es nicht.')
            ->assertSee('ds-admin-main', false)
            ->assertSee('href="'.route('pyramide.admin.index').'"', false);
    }

    #[Test]
    public function missing_players_and_challenges_without_any_ladder_never_link_to_a_second_404(): void
    {
        foreach (['/pyramide/spieler/9999', '/pyramide/forderung/9999'] as $url) {
            $this->get($url)
                ->assertNotFound()
                ->assertSee('href="'.route('calendar.index').'"', false)
                ->assertDontSee('Zur Pyramide');
        }
    }

    #[Test]
    public function members_who_administer_nothing_get_a_403_on_unknown_admin_ids_never_the_admin_shell(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $member = User::factory()->create();

        $requests = [
            ['GET', '/pyramide/admin/ladder/9999'],
            ['POST', '/pyramide/admin/ladder/9999/seed'],
            ['POST', '/pyramide/admin/player/9999/status'],
            ['POST', '/pyramide/admin/challenge/9999/override'],
        ];

        foreach ($requests as [$method, $uri]) {
            $this->actingAs($member)->call($method, $uri)
                ->assertForbidden()
                ->assertDontSee('ds-admin-main', false);
        }
    }

    #[Test]
    public function a_grant_holder_still_gets_the_admin_404_for_unknown_ids(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(2)->create();
        $holder = User::factory()->create();
        PyramidGrant::query()->create(['ladder_id' => $ladder->id, 'uid' => $holder->uid, 'role' => 'admin', 'created' => '2027-01-01 00:00:00']);

        $this->actingAs($holder)->get('/pyramide/admin/ladder/9999')
            ->assertNotFound()
            ->assertSee('ds-admin-main', false)
            ->assertSee('Diese Pyramide gibt es nicht.');
    }

    #[Test]
    public function json_requests_keep_the_json_404(): void
    {
        PyramidLadder::factory()->asDefault()->withRanking(3)->create();

        $this->getJson('/pyramide/999')->assertNotFound()->assertJson(['message' => 'Diese Pyramide gibt es nicht.']);
    }
}
