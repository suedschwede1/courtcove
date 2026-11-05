<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentMatch;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

/**
 * The match route exists with its final name and URL before the match page is ported (the cards link to
 * it): a match of the viewer's tournament answers 501 until then, a foreign or unknown id 404.
 */
class MatchRouteTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    #[Test]
    public function the_route_has_its_final_name_and_url(): void
    {
        $this->assertStringEndsWith('/tournament/match/7', route('tournament.match', ['tmid' => 7]));
    }

    #[Test]
    public function guests_are_sent_to_login(): void
    {
        $this->championship();

        $this->get('/tournament/match/1')->assertRedirect(route('login', ['redirect_to' => '/tournament/match/1']));
    }

    #[Test]
    public function own_tournament_match_is_not_implemented_yet_and_foreign_ids_404(): void
    {
        $this->championship();
        $other = Tournament::factory()->create(['season' => 2020]);
        $foreign = TournamentMatch::factory()->group('A', 1, 1)->create(['tid' => $other->tid]);
        $user = $this->member();

        $this->actingAs($user)->get('/tournament/match/'.$this->matchRow('A-1')->tmid)->assertStatus(501);
        $this->actingAs($user)->get('/tournament/match/'.$foreign->tmid)->assertNotFound();
        $this->actingAs($user)->get('/tournament/match/999999')->assertNotFound();
        $this->actingAs($user)->get('/tournament/match/x')->assertNotFound();
    }
}
