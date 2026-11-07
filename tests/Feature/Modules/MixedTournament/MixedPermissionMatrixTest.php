<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Models\User;
use App\Modules\MixedTournament\Models\MixedAvoidPair;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedEventGrant;
use App\Modules\MixedTournament\Models\MixedMember;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Role x route permission matrix of the whole mixed.* route table.
 *
 * GATES classifies every route by the access level it must demand; a route added without a
 * classification (or classified differently from its real middleware) fails
 * every_mixed_route_is_classified. The matrix then requests every route as every role.
 */
class MixedPermissionMatrixTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ROLES = ['guest', 'reader', 'score', 'event_admin', 'test_user', 'assist_global', 'admin'];

    /** @var array<string, list<string>> level => roles that may pass */
    private const ALLOWED = [
        'auth' => ['reader', 'score', 'event_admin', 'test_user', 'assist_global', 'admin'],
        'view' => ['reader', 'score', 'event_admin', 'test_user', 'assist_global', 'admin'],
        'score' => ['score', 'event_admin', 'test_user', 'assist_global', 'admin'],
        'administer' => ['event_admin', 'test_user', 'assist_global', 'admin'],
        'global' => ['assist_global', 'admin'],
    ];

    /** @var array<string, string> route name => level */
    private const GATES = [
        'mixed.index' => 'auth',
        'mixed.events' => 'auth',
        'mixed.info' => 'auth',
        'mixed.event' => 'view',
        'mixed.round' => 'view',
        'mixed.scorecard' => 'view',
        'mixed.scorecard-blank' => 'view',
        'mixed.durchmischung' => 'view',
        'mixed.standings' => 'view',
        'mixed.admin.index' => 'auth',
        'mixed.admin.create-event' => 'global',
        'mixed.admin.set-default' => 'global',
        'mixed.admin.set-test-mode' => 'global',
        'mixed.admin.grant' => 'global',
        'mixed.admin.revoke' => 'global',
        'mixed.admin.import-members' => 'global',
        'mixed.admin.import-members.store' => 'global',
        'mixed.admin.set-court-count' => 'administer',
        'mixed.admin.event' => 'administer',
        'mixed.admin.config' => 'administer',
        'mixed.admin.member-search' => 'administer',
        'mixed.admin.match-members' => 'administer',
        'mixed.admin.pause-all' => 'administer',
        'mixed.admin.add-participant' => 'administer',
        'mixed.admin.update-participant' => 'administer',
        'mixed.admin.participant-status' => 'administer',
        'mixed.admin.remove-participant' => 'administer',
        'mixed.admin.add-avoid-pair' => 'administer',
        'mixed.admin.remove-avoid-pair' => 'administer',
        'mixed.admin.new-round' => 'administer',
        'mixed.admin.create-round' => 'administer',
        'mixed.admin.round' => 'score',
        'mixed.admin.update-round-config' => 'administer',
        'mixed.admin.generate' => 'administer',
        'mixed.admin.swap' => 'administer',
        'mixed.admin.swap-sitter' => 'administer',
        'mixed.admin.confirm' => 'administer',
        'mixed.admin.complete' => 'administer',
        'mixed.admin.reopen' => 'administer',
        'mixed.admin.delete-round' => 'administer',
        'mixed.admin.score' => 'score',
        'mixed.admin.durchmischung' => 'administer',
        'mixed.admin.standings' => 'administer',
    ];

    /** @return array<string, array{string, string, bool}> */
    public static function matrix(): array
    {
        $cases = [];
        foreach (self::GATES as $name => $level) {
            foreach (self::ROLES as $role) {
                $cases[$name.' as '.$role] = [$name, $role, in_array($role, self::ALLOWED[$level], true)];
            }
        }

        return $cases;
    }

    /**
     * Event (test mode only for the test user), a round, a participant and a grant of someone else,
     * plus the user of the role.
     *
     * @return array{event: MixedEvent, round: MixedRound, participant: MixedParticipant, second: MixedParticipant, pair: MixedAvoidPair, other: User, user: ?User}
     */
    private function world(string $role): array
    {
        $event = MixedEvent::factory()->create(['is_test' => $role === 'test_user' ? 1 : 0]);
        $round = MixedRound::factory()->create(['event_id' => $event->id]);
        $participant = MixedParticipant::factory()->create(['event_id' => $event->id]);
        $second = MixedParticipant::factory()->create(['event_id' => $event->id]);
        $pair = MixedAvoidPair::query()->create(['event_id' => $event->id, 'participant_a' => $participant->id, 'participant_b' => $second->id]);
        $other = User::factory()->status('enabled')->create();
        MixedEventGrant::factory()->create(['event_id' => $event->id, 'uid' => $other->uid, 'role' => 'score']);

        return ['event' => $event, 'round' => $round, 'participant' => $participant, 'second' => $second, 'pair' => $pair, 'other' => $other, 'user' => $this->userFor($role, $event)];
    }

    private function userFor(string $role, MixedEvent $event): ?User
    {
        if ($role === 'guest') {
            return null;
        }

        if ($role === 'admin') {
            return User::factory()->status('admin')->create();
        }

        if ($role === 'assist_global') {
            $user = User::factory()->status('assist')->create();
            $user->setMeta('allow.admin.mixed', 'true');

            return $user->fresh();
        }

        $user = User::factory()->status('enabled')->create();
        if ($role === 'score' || $role === 'event_admin') {
            MixedEventGrant::factory()->create(['event_id' => $event->id, 'uid' => $user->uid, 'role' => $role === 'score' ? 'score' : 'admin']);
        }

        return $user;
    }

    private function route(string $name): LaravelRoute
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertNotNull($route, $name.' is not registered');

        return $route;
    }

    /**
     * @param  array{event: MixedEvent, round: MixedRound, participant: MixedParticipant, second: MixedParticipant, pair: MixedAvoidPair, other: User, user: ?User}  $world
     * @return array{string, string, array<string, mixed>} method, uri, body
     */
    private function request(string $name, array $world): array
    {
        $route = $this->route($name);
        $uri = str_replace(
            ['{event}', '{round}', '{participant}'],
            [(string) $world['event']->id, (string) $world['round']->id, (string) $world['participant']->id],
            $route->uri(),
        );
        $this->assertStringNotContainsString('{', $uri, $name.' has a parameter this test cannot fill');

        $body = match (true) {
            str_contains($route->uri(), '{participant}') => ['event_id' => $world['event']->id],
            $name === 'mixed.admin.swap' => ['participant_a' => $world['participant']->id, 'participant_b' => $world['second']->id],
            $name === 'mixed.admin.swap-sitter' => ['sitter_id' => $world['participant']->id, 'player_id' => $world['second']->id],
            $name === 'mixed.admin.remove-avoid-pair' => ['pair_id' => $world['pair']->id],
            $name === 'mixed.admin.revoke' => ['uid' => $world['other']->uid],
            default => [],
        };

        return [in_array('POST', $route->methods(), true) ? 'POST' : 'GET', '/'.$uri, $body];
    }

    private function isLoginRedirect(TestResponse $response): bool
    {
        return $response->isRedirect() && str_starts_with((string) $response->headers->get('Location'), route('login'));
    }

    #[Test]
    #[DataProvider('matrix')]
    public function role_route_matrix(string $name, string $role, bool $allowed): void
    {
        $world = $this->world($role);
        [$method, $uri, $body] = $this->request($name, $world);

        if ($world['user'] !== null) {
            $this->actingAs($world['user']);
        }

        // call() sends no default cookies: pass the admin mode cookie (global rights only count in admin mode).
        $response = $this->call($method, $uri, $body, ['admin_mode' => '1']);
        $label = $method.' '.$uri.' as '.$role;

        if ($role === 'guest') {
            $this->assertTrue($this->isLoginRedirect($response), $label.' must redirect to the login, got '.$response->getStatusCode());

            return;
        }

        $this->assertFalse($this->isLoginRedirect($response), $label.' must not bounce a logged-in user to the login');

        if ($allowed) {
            $this->assertNotContains($response->getStatusCode(), [403, 404], $label.' must pass authorization');
            $this->assertLessThan(500, $response->getStatusCode(), $label.' must not fail');
        } else {
            $this->assertSame(403, $response->getStatusCode(), $label.' must be forbidden');
        }
    }

    #[Test]
    public function every_mixed_route_is_classified(): void
    {
        $registered = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            /** @var LaravelRoute $route */
            $name = (string) $route->getName();
            if (str_starts_with($name, 'mixed.')) {
                $registered[] = $name;
            }
        }
        sort($registered);
        $classified = array_keys(self::GATES);
        sort($classified);

        $this->assertSame([], array_values(array_diff($registered, $classified)), 'routes without a classification in GATES');
        $this->assertSame([], array_values(array_diff($classified, $registered)), 'classified routes that no longer exist');
        $this->assertGreaterThanOrEqual(42, count($registered));

        // The classification must match the real middleware: login everywhere, the global right exactly on 'global'.
        foreach (self::GATES as $name => $level) {
            $middleware = array_map('strval', $this->route($name)->gatherMiddleware());
            $this->assertNotEmpty(preg_grep('/^tournament-base\.login/', $middleware), $name.' lacks the login middleware');
            $this->assertSame($level === 'global', in_array('can:admin.mixed', $middleware, true), $name.' global middleware does not match its classification');
        }

        // The allowed-role table itself: every level is known and the global level excludes test mode and event grants.
        foreach (array_unique(array_values(self::GATES)) as $level) {
            $this->assertArrayHasKey($level, self::ALLOWED);
        }
        $this->assertSame(['assist_global', 'admin'], self::ALLOWED['global']);
    }

    #[Test]
    public function test_mode_user_cannot_reach_global_actions(): void
    {
        $world = $this->world('test_user');
        $event = $world['event'];
        $user = $world['user'];
        $this->assertInstanceOf(User::class, $user);
        $victim = User::factory()->status('enabled')->create();
        $events = MixedEvent::query()->count();
        $members = MixedMember::query()->count();
        $this->actingAs($user);

        // Sanity: in test mode the user is event admin (event pages open), but never global admin.
        $this->get('/mixed-tournament/admin/event/'.$event->id.'/config')->assertOk();

        $this->post('/mixed-tournament/admin/event/'.$event->id.'/set-test-mode', ['is_test' => '0'])->assertForbidden();
        $this->post('/mixed-tournament/admin/event/'.$event->id.'/grant', ['uid' => $victim->uid, 'role' => 'admin'])->assertForbidden();
        $this->post('/mixed-tournament/admin/event/'.$event->id.'/revoke', ['uid' => $world['other']->uid])->assertForbidden();
        $this->post('/mixed-tournament/admin/event/'.$event->id.'/set-default')->assertForbidden();
        $this->post('/mixed-tournament/admin/create-event', ['title' => 'Beispiel', 'event_date' => '2027-05-01', 'mode' => 'americano'])->assertForbidden();
        $this->get('/mixed-tournament/admin/import-members')->assertForbidden();
        $this->post('/mixed-tournament/admin/import-members')->assertForbidden();

        $fresh = $event->fresh();
        $this->assertSame(1, (int) $fresh->is_test);
        $this->assertSame(0, (int) $fresh->is_default);
        $this->assertFalse(MixedEventGrant::query()->where('event_id', $event->id)->where('uid', $victim->uid)->exists());
        $this->assertTrue(MixedEventGrant::query()->where('event_id', $event->id)->where('uid', $world['other']->uid)->exists());
        $this->assertSame($events, MixedEvent::query()->count());
        $this->assertSame($members, MixedMember::query()->count());
    }

    #[Test]
    public function test_mode_on_one_event_gives_no_rights_on_another(): void
    {
        $world = $this->world('test_user');
        $normal = MixedEvent::factory()->create(['is_test' => 0]);
        $normalRound = MixedRound::factory()->create(['event_id' => $normal->id]);

        $this->actingAs($world['user']);

        $this->get('/mixed-tournament/admin/event/'.$normal->id)->assertForbidden();
        $this->get('/mixed-tournament/admin/round/'.$normalRound->id)->assertForbidden();
        $this->get('/mixed-tournament/event/'.$normal->id)->assertOk();
    }

    #[Test]
    public function score_grant_on_one_event_does_not_carry_to_another(): void
    {
        $a = MixedEvent::factory()->create();
        $b = MixedEvent::factory()->create();
        $roundA = MixedRound::factory()->create(['event_id' => $a->id]);
        $roundB = MixedRound::factory()->create(['event_id' => $b->id]);
        $user = User::factory()->status('enabled')->create();
        MixedEventGrant::factory()->create(['event_id' => $a->id, 'uid' => $user->uid, 'role' => 'score']);
        $this->actingAs($user);

        $this->get('/mixed-tournament/admin/round/'.$roundA->id)->assertOk();
        $this->post('/mixed-tournament/admin/round/'.$roundA->id.'/score')->assertStatus(302);

        $this->get('/mixed-tournament/admin/round/'.$roundB->id)->assertForbidden();
        $this->post('/mixed-tournament/admin/round/'.$roundB->id.'/score')->assertForbidden();
        $this->get('/mixed-tournament/admin/event/'.$a->id)->assertForbidden();
        $this->get('/mixed-tournament/event/'.$b->id)->assertOk();
    }

    #[Test]
    public function an_admin_grant_on_one_event_does_not_carry_to_another_nor_to_global_actions(): void
    {
        $a = MixedEvent::factory()->create();
        $b = MixedEvent::factory()->create();
        $user = User::factory()->status('enabled')->create();
        MixedEventGrant::factory()->create(['event_id' => $a->id, 'uid' => $user->uid, 'role' => 'admin']);
        $this->actingAs($user);

        $this->get('/mixed-tournament/admin/event/'.$a->id)->assertOk();
        $this->get('/mixed-tournament/admin/event/'.$b->id)->assertForbidden();
        $this->post('/mixed-tournament/admin/event/'.$a->id.'/grant', ['uid' => $user->uid, 'role' => 'admin'])->assertForbidden();
        $this->post('/mixed-tournament/admin/event/'.$a->id.'/set-test-mode', ['is_test' => '1'])->assertForbidden();
        $this->assertSame(0, (int) $a->fresh()->is_test);
    }

    #[Test]
    public function a_real_session_login_carries_the_roles_through_the_whole_middleware_stack(): void
    {
        $event = MixedEvent::factory()->create();
        $round = MixedRound::factory()->create(['event_id' => $event->id]);
        User::factory()->create(['email' => 'matrix@example.test', 'pw' => bcrypt('secret123'), 'status' => 'enabled']);

        $this->get('/mixed-tournament/admin/event/'.$event->id)->assertRedirect();
        $this->assertGuest();

        $this->post('/login', ['email' => 'matrix@example.test', 'password' => 'secret123']);
        $this->assertAuthenticated();

        $this->get('/mixed-tournament/event/'.$event->id)->assertOk();
        $this->get('/mixed-tournament/admin/event/'.$event->id)->assertForbidden();
        $this->get('/mixed-tournament/admin/round/'.$round->id)->assertForbidden();
        $this->post('/mixed-tournament/admin/create-event')->assertForbidden();
    }
}
