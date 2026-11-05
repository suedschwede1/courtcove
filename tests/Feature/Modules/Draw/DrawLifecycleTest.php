<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Models\User;
use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Services\DrawLifecycleService;
use App\Modules\TournamentBase\Support\ActionRefused;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakeBeaconService;
use Tests\TestCase;

class DrawLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const GENESIS = 1700000000;

    private FakeBeaconService $beacon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->beacon = new FakeBeaconService(self::GENESIS, 30, [1000 => str_repeat('5e', 32)]);
        $this->app->instance(BeaconService::class, $this->beacon);
        Date::setTestNow(Date::createFromTimestamp(self::GENESIS + 1000 * 30));
    }

    private function admin(): User
    {
        return User::factory()->status('admin')->create();
    }

    private function refusalText(callable $action): string
    {
        try {
            $action();
        } catch (ActionRefused $e) {
            return (string) __($e->key, $e->replace);
        }

        $this->fail('no refusal');
    }

    #[Test]
    public function open_only_from_executed_token(): void
    {
        $service = app(DrawLifecycleService::class);
        $order = 'Die Wahlphase kann erst geöffnet werden, wenn die Ziehung ausgeführt ist. Reihenfolge: festschreiben, ausführen, Wahlphase öffnen.';

        foreach ([
            Draw::factory()->token()->create(),                                         // committed
            Draw::factory()->executed()->create(),                                      // direct
            Draw::factory()->token()->executed()->create(),                             // already picking
            Draw::factory()->token()->executed()->discarded()->create(['status' => 'executed']),
        ] as $draw) {
            $before = Draw::query()->findOrFail($draw->did)->getAttributes();
            $this->assertSame($order, $this->refusalText(fn () => $service->open($draw, null)));
            $this->assertSame($before, Draw::query()->findOrFail($draw->did)->getAttributes());
        }

        // Legacy path: a token draw still in 'executed'.
        $legacy = Draw::factory()->token()->executed()->create(['status' => 'executed', 'pick_deadline' => '2026-11-10 18:00:00']);
        $service->open($legacy, '');
        $this->assertSame(['picking', '2026-11-10 18:00:00'], [$legacy->fresh()->status, $legacy->fresh()->pick_deadline]);

        $legacy2 = Draw::factory()->token()->executed()->create(['status' => 'executed']);
        $service->open($legacy2, '2026-11-12T20:30');
        $this->assertSame(['picking', '2026-11-12 20:30:00'], [$legacy2->fresh()->status, $legacy2->fresh()->pick_deadline]);

        $legacy3 = Draw::factory()->token()->executed()->create(['status' => 'executed']);
        $this->assertSame('Bitte eine gültige Frist eintragen.', $this->refusalText(fn () => $service->open($legacy3, '2026-02-30T20:00')));
        $this->assertSame('Bitte eine gültige Frist eintragen.', $this->refusalText(fn () => $service->open($legacy3, 'morgen')));
        $this->assertSame('executed', $legacy3->fresh()->status);
    }

    #[Test]
    public function delete_requires_discarded_unexecuted_unclaimed(): void
    {
        $service = app(DrawLifecycleService::class);

        $running = Draw::factory()->token()->create();
        $this->assertSame('Nur eine verworfene Ziehung kann gelöscht werden. Setze zuerst in der Turnierleitung zurück.', $this->refusalText(fn () => $service->delete($running)));

        $executed = Draw::factory()->executed()->discarded()->create();
        $this->assertSame('Diese Ziehung wurde ausgeführt und bleibt deshalb erhalten. Ein einmal gezogenes Ergebnis soll nachlesbar bleiben, auch wenn es verworfen wurde.', $this->refusalText(fn () => $service->delete($executed)));

        $claimed = Draw::factory()->token()->discarded()->create();
        DrawToken::query()->where('did', $claimed->did)->where('position', 1)->update(['claimed_tpid' => 501, 'claimed_uid' => 601]);
        DrawToken::query()->where('did', $claimed->did)->where('position', 2)->update(['claimed_tpid' => 502, 'claimed_uid' => 602]);
        $this->assertSame('2 Token(s) sind belegt - die Ziehung bleibt erhalten, damit die Wahl der Teilnehmer nicht spurlos verschwindet.', $this->refusalText(fn () => $service->delete($claimed)));

        $this->assertSame(3, Draw::query()->count());

        $gone = Draw::factory()->token()->discarded()->create(['title' => 'Verworfene Demo']);
        DrawEligible::query()->create(['did' => $gone->did, 'tpid' => 11]);
        $other = Draw::factory()->token()->create();

        $flash = $service->delete($gone);

        $this->assertSame('Ziehung "Verworfene Demo" gelöscht. Sie wurde nie ausgeführt, es gab also kein Ergebnis zu bewahren.', $flash);
        $this->assertNull(Draw::query()->find($gone->did));
        $this->assertSame(0, DrawToken::query()->where('did', $gone->did)->count());
        $this->assertSame(0, DrawEligible::query()->where('did', $gone->did)->count());
        $this->assertSame(6, DrawToken::query()->where('did', $other->did)->count());
    }

    #[Test]
    public function controller_actions_flash_and_redirect(): void
    {
        $admin = $this->admin();
        $draw = Draw::factory()->create();

        $this->actingAs($admin)->post(route('draw.execute', ['did' => $draw->did]))
            ->assertRedirect(route('draw.index'))
            ->assertSessionHas('success', 'Ziehung ausgeführt. Protokoll unter '.route('draw.record', ['slug' => $draw->slug]));
        $this->assertSame('executed', $draw->fresh()->status);

        $this->actingAs($admin)->post(route('draw.execute', ['did' => $draw->did]))
            ->assertSessionHas('error', 'Diese Ziehung wurde bereits ausgeführt und kann nicht wiederholt werden.');

        $this->actingAs($admin)->post(route('draw.execute', ['did' => 999999]))
            ->assertRedirect(route('draw.index'))
            ->assertSessionHas('error', 'Ziehung nicht gefunden oder verworfen.');
        $this->actingAs($admin)->post(route('draw.delete', ['did' => 999999]))
            ->assertSessionHas('error', 'Ziehung nicht gefunden.');
        $this->actingAs($admin)->post(route('draw.open', ['did' => 999999]))
            ->assertSessionHas('error', 'Die Wahlphase kann erst geöffnet werden, wenn die Ziehung ausgeführt ist. Reihenfolge: festschreiben, ausführen, Wahlphase öffnen.');

        $legacy = Draw::factory()->token()->executed()->create(['status' => 'executed']);
        $this->actingAs($admin)->post(route('draw.open', ['did' => $legacy->did]), ['deadline' => ['x']])
            ->assertSessionHas('success', 'Wahlphase offen. Die Teilnehmer können jetzt Tokens wählen.');
        $this->assertSame('picking', $legacy->fresh()->status);

        $discarded = Draw::factory()->discarded()->create(['title' => 'Weg Demo']);
        $this->actingAs($admin)->post(route('draw.delete', ['did' => $discarded->did]))
            ->assertSessionHas('success', 'Ziehung "Weg Demo" gelöscht. Sie wurde nie ausgeführt, es gab also kein Ergebnis zu bewahren.');
    }

    #[Test]
    public function the_overview_offers_the_matching_action_forms(): void
    {
        $committed = Draw::factory()->token()->create();
        $legacy = Draw::factory()->token()->executed()->create(['status' => 'executed', 'pick_deadline' => '2026-11-10 18:00:00']);
        $discarded = Draw::factory()->discarded()->create();
        $direct = Draw::factory()->executed()->create();

        $html = (string) $this->actingAs($this->admin())->get(route('draw.index'))->assertOk()->getContent();
        $confirms = collect($this->confirms($html))->keyBy('url');

        $this->assertSame([
            route('draw.delete', ['did' => $discarded->did]),
            route('draw.open', ['did' => $legacy->did]),
            route('draw.execute', ['did' => $committed->did]),
        ], $confirms->keys()->all());
        $this->assertSame('2026-11-10T18:00', $confirms[route('draw.open', ['did' => $legacy->did])]['deadline']);
        $this->assertNull($confirms[route('draw.execute', ['did' => $committed->did])]['deadline']);
        $this->assertSame('Ziehung jetzt ausführen? Das ist nicht wiederholbar.', $confirms[route('draw.execute', ['did' => $committed->did])]['text']);
        $this->assertStringContainsString('id="draw-confirm-form"', $html);
        $this->assertMatchesRegularExpression('/<form method="POST" id="draw-confirm-form"[^>]*>\s*<input type="hidden" name="_token"/', $html);
    }

    #[Test]
    public function get_on_action_paths_redirects(): void
    {
        $admin = $this->admin();
        foreach (['open', 'close', 'delete', 'execute'] as $action) {
            $this->actingAs($admin)->get('/draw/'.$action.'/5')->assertRedirect(route('draw.index'));
        }
        $this->actingAs($admin)->get('/draw/execute/abc')->assertNotFound();
    }

    #[Test]
    public function routes_require_admin_draw_and_csrf(): void
    {
        $draw = Draw::factory()->discarded()->create();
        $member = User::factory()->create();
        $paths = ['draw.execute', 'draw.open', 'draw.delete'];

        foreach ($paths as $name) {
            $this->post(route($name, ['did' => $draw->did]))
                ->assertRedirect(route('login', ['redirect_to' => '/draw/'.substr($name, 5).'/'.$draw->did]));
            $this->actingAs($member)->post(route($name, ['did' => $draw->did]))->assertForbidden();
            // Authorization comes before id resolution: an unknown id is still a 403 for a member.
            $this->actingAs($member)->post(route($name, ['did' => 999999]))->assertForbidden();
            auth()->logout();
        }
        $this->assertNotNull($draw->fresh());

        $assist = User::factory()->status('assist')->create();
        $assist->setMeta('allow.admin.draw', 'true');
        $this->actingAs($assist->fresh())->post(route('draw.delete', ['did' => $draw->did]))->assertSessionHas('success');
        $this->assertNull(Draw::query()->find($draw->did));

        // Every POST route of the admin group is in the web group, not CSRF-excluded and gated.
        $web = app('router')->getMiddlewareGroups()['web'];
        $csrf = array_values(array_filter($web, fn ($m) => is_string($m) && class_exists($m) && is_a($m, PreventRequestForgery::class, true)));
        $this->assertNotEmpty($csrf);
        $excluded = app($csrf[0])->getExcludedPaths();
        $posts = array_filter(Route::getRoutes()->getRoutes(), fn (RoutingRoute $r) => str_starts_with($r->uri(), 'draw/') && ! str_starts_with($r->uri(), 'draw/tokens/') && in_array('POST', $r->methods(), true));
        $this->assertCount(4, $posts); // commit, open, delete, execute (close: Task 29); the player pick is in TokenPickTest
        foreach ($posts as $route) {
            $this->assertContains('web', $route->gatherMiddleware(), $route->uri());
            $this->assertContains('can:admin.draw', $route->gatherMiddleware(), $route->uri());
            $this->assertContains('tournament-base.login:draw::common.login_required', $route->gatherMiddleware(), $route->uri());
            $this->assertNotContains('auth', $route->gatherMiddleware(), $route->uri());
            foreach ($excluded as $pattern) {
                $this->assertFalse(Request::create('/'.$route->uri(), 'POST')->is($pattern === '/' ? $pattern : trim((string) $pattern, '/')));
            }
        }

        // Behaviour: with verification active a token-less post is refused and nothing changes.
        $target = Draw::factory()->create();
        $this->withMiddleware();
        $this->app['env'] = 'local';
        $this->actingAs($this->admin())->from(route('draw.index'))
            ->post(route('draw.execute', ['did' => $target->did]))
            ->assertRedirect(route('draw.index'))
            ->assertSessionHas('error');
        $this->assertNull($target->fresh()->final_seed);
    }

    #[Test]
    public function a_real_login_session_can_execute(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['pw' => bcrypt('geheim-123')])->save();
        $draw = Draw::factory()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'geheim-123']);
        $this->post(route('draw.execute', ['did' => $draw->did]))->assertRedirect(route('draw.index'));

        $this->assertSame('executed', $draw->fresh()->status);
    }

    /**
     * The confirm payloads of the action buttons (`pending = JSON.parse('...')`), decoded.
     *
     * @return list<array{url: string, text: string, submit: string, deadline: ?string}>
     */
    private function confirms(string $html): array
    {
        preg_match_all('/pending = JSON\.parse\(&#039;(.*?)&#039;\)/', $html, $m);

        return array_map(static fn (string $literal): array => json_decode((string) json_decode('"'.$literal.'"'), true), $m[1]);
    }
}
