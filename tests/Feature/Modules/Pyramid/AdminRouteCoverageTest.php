<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminRouteCoverageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Every administration route with ids of a ladder the reader has no grant for.
     *
     * @return list<array{0: string, 1: string}> method and URI
     */
    private function adminRequests(PyramidLadder $ladder, PyramidPlayer $player, PyramidChallenge $challenge): array
    {
        $requests = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            /** @var LaravelRoute $route */
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'pyramide.admin.')) {
                continue;
            }

            $uri = str_replace(['{ladderId}', '{playerId}', '{challengeId}'], [(string) $ladder->id, (string) $player->id, (string) $challenge->id], $route->uri());
            $this->assertStringNotContainsString('{', $uri, $name.' has a parameter this test cannot fill');

            foreach ($route->methods() as $method) {
                if (in_array($method, ['GET', 'POST'], true)) {
                    $requests[] = [$method, '/'.$uri];
                }
            }
        }

        return $requests;
    }

    private function fixtures(): array
    {
        $ladder = PyramidLadder::factory()->withRanking(4)->create();
        $player = PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', 2)->firstOrFail();
        $challenger = PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', 3)->firstOrFail();
        $challenge = PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id, 'challenger_id' => $challenger->id, 'opponent_id' => $player->id,
            'challenger_pos' => 3, 'opponent_pos' => 2, 'status' => 'open',
        ]);

        return [$ladder, $player, $challenge];
    }

    #[Test]
    public function every_admin_route_requires_login_and_the_policy(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        [$ladder, $player, $challenge] = $this->fixtures();
        $requests = $this->adminRequests($ladder, $player, $challenge);
        $reader = User::factory()->create();

        $this->assertGreaterThanOrEqual(19, count($requests), 'the admin route table shrank or the name prefix changed');

        foreach ($requests as [$method, $uri]) {
            $this->call($method, $uri)->assertRedirect();
        }

        $this->actingAs($reader);

        foreach ($requests as [$method, $uri]) {
            $response = $this->call($method, $uri);
            $this->assertSame(403, $response->getStatusCode(), $method.' '.$uri.' must be forbidden for a reader');
        }

        $this->assertSame(0, PyramidChallenge::query()->where('status', '!=', 'open')->count());
        $this->assertSame(4, PyramidPlayer::query()->where('ladder_id', $ladder->id)->whereNotNull('position')->count());
    }

    #[Test]
    public function every_form_action_of_the_admin_pages_resolves_to_a_registered_post_route(): void
    {
        $admin = User::factory()->status('admin')->create();
        [$ladder, $player, $challenge] = $this->fixtures();

        PyramidPlayer::factory()->waiting()->create(['ladder_id' => $ladder->id, 'label' => 'Demo Spieler 91']);
        $challenge->update(['deadline' => now()->subDays(2)->format('Y-m-d')]);
        PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id, 'challenger_id' => $player->id, 'opponent_id' => $player->id + 1,
            'challenger_pos' => 2, 'opponent_pos' => 1, 'status' => 'played', 'winner_id' => $player->id, 'score' => '6:3 6:3',
            'resolved' => '2020-01-05 12:00:00',
        ]);
        PyramidLadder::factory()->create();

        $postUris = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            /** @var LaravelRoute $route */
            if (in_array('POST', $route->methods(), true)) {
                $postUris[] = '/'.ltrim((string) preg_replace('~\{[^}]+\}~', '{}', $route->uri()), '/');
            }
        }

        $pages = [
            '/pyramide/admin',
            '/pyramide/admin/ladder/'.$ladder->id,
            '/pyramide/admin/ladder/'.PyramidLadder::factory()->setup()->create()->id,
        ];
        $seen = 0;

        foreach ($pages as $page) {
            $html = $this->actingAs($admin)->get($page)->assertOk()->getContent();
            preg_match_all('~<form\b[^>]*\bmethod="POST"[^>]*\baction="([^"]+)"~i', $html, $forms);

            foreach ($forms[1] as $action) {
                $path = '/'.trim((string) parse_url(html_entity_decode($action), PHP_URL_PATH), '/');
                $path = (string) preg_replace('~^/booking(?=/)~', '', $path);
                $generic = (string) preg_replace('~/\d+(?=/|$)~', '/{}', $path);

                $this->assertContains($generic, $postUris, $page.' renders a form to '.$path.' without a POST route');
                $seen++;
            }
        }

        $this->assertGreaterThanOrEqual(12, $seen, 'too few forms were rendered to prove coverage');
    }
}
