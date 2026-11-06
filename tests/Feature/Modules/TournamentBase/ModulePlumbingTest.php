<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TournamentBase;

use App\Models\User;
use App\Modules\TournamentBase\Support\MemberSearch;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModulePlumbingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'tournament-base.login:tournament-base::common.area'])
            ->get('pyramide/_probe', fn () => 'ok')->name('pyramide.probe');
        Route::middleware(['web', 'tournament-base.login:tournament-base::common.area'])
            ->post('pyramide/_probe', fn () => 'ok')->name('pyramide.probe.post');
        Route::middleware('web')->post('other/_probe', fn () => 'ok')->name('other.probe');
    }

    #[Test]
    public function guest_is_sent_to_login_with_return_path_and_flash(): void
    {
        $response = $this->get('/pyramide/_probe?x=1');

        $response->assertRedirect(route('login', ['redirect_to' => '/pyramide/_probe?x=1']));
        $response->assertSessionHas('error', 'Turnierbereich');
    }

    /** @return array<string,string> */
    private function bookingBase(): array
    {
        return ['SCRIPT_NAME' => '/booking/index.php', 'SCRIPT_FILENAME' => base_path('public/index.php'), 'PHP_SELF' => '/booking/index.php'];
    }

    #[Test]
    public function login_redirect_keeps_base_path_and_query_for_get(): void
    {
        $response = $this->withServerVariables($this->bookingBase())->get('/booking/pyramide/_probe?x=1');

        $response->assertRedirect(route('login', ['redirect_to' => '/booking/pyramide/_probe?x=1']));
    }

    #[Test]
    public function login_redirect_keeps_base_path_but_drops_query_for_post(): void
    {
        $response = $this->withServerVariables($this->bookingBase())->post('/booking/pyramide/_probe?x=1');

        $response->assertRedirect(route('login', ['redirect_to' => '/booking/pyramide/_probe']));
    }

    #[Test]
    public function logged_in_user_passes_the_login_middleware(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']))
            ->get('/pyramide/_probe')->assertOk();
    }

    #[Test]
    public function expired_csrf_on_a_module_route_redirects_back_with_the_old_text(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']));
        $this->withMiddleware();
        $this->app['env'] = 'local'; // CSRF verification is skipped while the env is "testing"

        $response = $this->from(url('/pyramide'))->post('/pyramide/_probe');

        $response->assertRedirect(url('/pyramide'));
        $response->assertSessionHas('error', 'Ungültiger oder abgelaufener Formularschlüssel. Bitte Seite neu laden.');
    }

    #[Test]
    public function expired_csrf_without_referer_goes_to_login(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']));
        $this->withMiddleware();
        $this->app['env'] = 'local';

        $this->post('/pyramide/_probe')->assertRedirect(route('login'));
    }

    #[Test]
    public function expired_csrf_with_foreign_referer_goes_to_login(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']));
        $this->withMiddleware();
        $this->app['env'] = 'local';

        $this->from('https://evil.example.net/page')->post('/pyramide/_probe')->assertRedirect(route('login'));
    }

    #[Test]
    public function expired_csrf_for_json_requests_stays_419(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']));
        $this->withMiddleware();
        $this->app['env'] = 'local';

        $this->from('/pyramide')->postJson('/pyramide/_probe')->assertStatus(419);
    }

    #[Test]
    public function expired_csrf_elsewhere_stays_419(): void
    {
        $this->withMiddleware();
        $this->app['env'] = 'local'; // CSRF verification is skipped while the env is "testing"

        $this->from('/pyramide')->post('/other/_probe')->assertStatus(419);
    }

    #[Test]
    public function member_search_never_returns_phone_or_deleted_users(): void
    {
        $match = User::factory()->create(['uid' => 42, 'alias' => 'Demo Spieler 01', 'email' => 'one@example.test', 'status' => 'enabled']);
        User::factory()->create(['alias' => 'Demo Spieler 02', 'email' => 'two@example.test', 'status' => 'deleted']);
        User::factory()->create(['alias' => 'Demo Spieler 03', 'email' => 'three@example.test', 'status' => 'disabled']);

        $search = new MemberSearch;
        $found = $search->search('Demo Spieler');

        $this->assertCount(1, $found);
        $this->assertSame(['uid', 'alias', 'email'], array_keys($found[0]));
        $this->assertSame($match->uid, $found[0]['uid']);
        $this->assertSame([], $search->search('a'));
        $this->assertSame([], $search->search('  d '));
        $this->assertSame([$match->uid], array_column($search->search((string) $match->uid), 'uid'));
        $this->assertSame([$match->uid], array_column($search->search('one@example'), 'uid'));
    }

    #[Test]
    public function member_search_treats_like_wildcards_literally(): void
    {
        User::factory()->create(['alias' => 'Demo Spieler 01', 'email' => 'one@example.test', 'status' => 'enabled']);
        User::factory()->create(['alias' => 'Demo_Spieler%X', 'email' => 'two@example.test', 'status' => 'enabled']);

        $search = new MemberSearch;

        $this->assertSame([], $search->search('%%'));
        $this->assertSame([], $search->search('__'));
        $this->assertSame(['Demo_Spieler%X'], array_column($search->search('o_S'), 'alias'));
        $this->assertSame(['Demo_Spieler%X'], array_column($search->search('r%X'), 'alias'));
    }

    #[Test]
    public function member_search_orders_by_alias_and_honours_the_limit(): void
    {
        User::factory()->create(['alias' => 'Demo Spieler 12', 'status' => 'enabled']);
        User::factory()->create(['alias' => 'Demo Spieler 11', 'status' => 'enabled']);
        User::factory()->create(['alias' => 'Demo Spieler 13', 'status' => 'enabled']);

        $found = (new MemberSearch)->search('Demo Spieler', 2);

        $this->assertSame(['Demo Spieler 11', 'Demo Spieler 12'], array_column($found, 'alias'));
    }

    #[Test]
    public function the_module_tab_and_info_partials_are_gone(): void
    {
        // The tournament sidebar is the module navigation and the area shell shows the info flash
        // (TournamentSidebarTest pins both).
        $this->assertFalse(view()->exists('tournament-base::partials.module-tabs'));
        $this->assertFalse(view()->exists('tournament-base::partials.info-notice'));
    }

    #[Test]
    public function pagination_shows_summary_and_current_page(): void
    {
        $paginator = new LengthAwarePaginator(['a', 'b'], 50, 2, 3, ['path' => '/list']);

        $html = view('tournament-base::partials.pagination', [
            'paginator' => $paginator,
            'summary' => 'Seite 3 von 25',
        ])->render();

        $this->assertStringContainsString('Seite 3 von 25', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
    }
}
