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
 * The match route keeps its final name and URL (the cards link to it): a match of the viewer's tournament
 * opens the match page, a foreign or unknown id answers 404.
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
    public function own_tournament_match_opens_and_foreign_ids_404(): void
    {
        $this->championship();
        $other = Tournament::factory()->create(['season' => 2020]);
        $foreign = TournamentMatch::factory()->group('A', 1, 1)->create(['tid' => $other->tid]);
        $user = $this->member();

        $this->actingAs($user)->get('/tournament/match/'.$this->matchRow('A-1')->tmid)->assertOk();
        $this->actingAs($user)->get('/tournament/match/'.$foreign->tmid)->assertNotFound();
        $this->actingAs($user)->get('/tournament/match/999999')->assertNotFound();
        $this->actingAs($user)->get('/tournament/match/x')->assertNotFound();
    }
}
