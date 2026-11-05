<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Support\PyramidNotFound;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * An unknown ladder (and a missing player or challenge) shows a friendly page of the module, still with status 404.
 */
class PyramidNotFoundPageTest extends TestCase
{
    use LazilyRefreshDatabase;

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
            ->assertSee('ds-header', false);
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
    public function json_requests_keep_the_json_404(): void
    {
        PyramidLadder::factory()->asDefault()->withRanking(3)->create();

        $this->getJson('/pyramide/999')->assertNotFound()->assertJson(['message' => 'Diese Pyramide gibt es nicht.']);
    }
}
