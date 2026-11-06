<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TournamentBase;

use App\Models\User;
use App\Modules\Draw\Database\Seeders\DrawDemoSeeder;
use App\Modules\Pyramid\Database\Seeders\PyramidDemoSeeder;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Tournament\Database\Seeders\TournamentDemoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Club decision 2026-11-06: every tournament page needs a login (config tournament-base.require_login, default on).
 * Guests are sent to the login with the return path, a JSON request gets a 401; with the setting off the pages that
 * can be public (Pyramid ranking, rules, challenges, player and challenge pages, the draw record and its JSON) are
 * public again.
 */
class TournamentLoginRequiredTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, string> label => URL of the pages that can be public
     */
    private function publicPages(): array
    {
        $this->seed(PyramidDemoSeeder::class);
        $this->seed(TournamentDemoSeeder::class);
        $this->seed(DrawDemoSeeder::class);
        $ladder = PyramidLadder::query()->orderBy('id')->firstOrFail();
        $player = PyramidPlayer::query()->where('ladder_id', $ladder->id)->orderBy('id')->firstOrFail();
        $challenge = PyramidChallenge::query()->orderBy('id')->firstOrFail();

        return [
            'pyramid index' => route('pyramide.index'),
            'pyramid ladder' => route('pyramide.ladder', ['ladderId' => $ladder->id]),
            'pyramid rules' => route('pyramide.rules'),
            'pyramid challenges' => route('pyramide.challenges'),
            'pyramid player' => route('pyramide.player', ['playerId' => $player->id]),
            'pyramid challenge' => route('pyramide.challenge.show', ['challengeId' => $challenge->id]),
            'draw record' => route('draw.record', ['slug' => 'demo-direct']),
        ];
    }

    #[Test]
    public function the_setting_defaults_to_on(): void
    {
        $this->assertTrue(config('tournament-base.require_login'));
    }

    #[Test]
    public function guests_are_sent_to_the_login_from_every_parameterless_page(): void
    {
        $names = [];
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (preg_match('/^(pyramide|tournament|draw|mixed)\./', $name) && in_array('GET', $route->methods(), true)
                && ! preg_match('/\{[^}?]+\}/', $route->uri())) {
                $names[] = $name;
            }
        }
        $this->assertGreaterThan(15, count($names));

        foreach ($names as $name) {
            $response = $this->get(route($name));
            $response->assertRedirect();
            $this->assertStringStartsWith(route('login'), (string) $response->headers->get('Location'), $name);
            $this->assertStringContainsString('redirect_to=', (string) $response->headers->get('Location'), $name);
        }
    }

    #[Test]
    public function guests_are_sent_to_the_login_from_the_public_pages_with_the_return_path(): void
    {
        foreach ($this->publicPages() as $label => $url) {
            $path = (string) parse_url($url, PHP_URL_PATH);
            $this->get($url)->assertRedirect(route('login', ['redirect_to' => $path]));
        }
    }

    #[Test]
    public function the_record_json_answers_a_guest_with_401(): void
    {
        $this->seed(TournamentDemoSeeder::class);
        $this->seed(DrawDemoSeeder::class);

        $this->get(route('draw.record.json', ['slug' => 'demo-direct']))->assertUnauthorized()->assertJsonStructure(['message']);
        $this->getJson(route('draw.record', ['slug' => 'demo-direct']))->assertUnauthorized();
    }

    #[Test]
    public function logged_in_users_open_the_public_pages(): void
    {
        $user = User::factory()->status('enabled')->create();

        foreach ($this->publicPages() as $label => $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
        $this->actingAs($user)->get(route('draw.record.json', ['slug' => 'demo-direct']))->assertOk()->assertHeader('Content-Type', 'application/json');
    }

    #[Test]
    public function with_the_setting_off_the_public_pages_are_public_again(): void
    {
        config(['tournament-base.require_login' => false]);

        foreach ($this->publicPages() as $label => $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('draw.record.json', ['slug' => 'demo-direct']))->assertOk();

        // The other pages keep their login.
        foreach (['mixed.index', 'tournament.index', 'draw.index'] as $name) {
            $this->get(route($name))->assertRedirect();
        }
    }
}
