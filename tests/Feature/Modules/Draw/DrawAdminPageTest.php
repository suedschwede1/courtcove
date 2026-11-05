<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Models\User;
use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Models\Draw;
use App\Services\UiLinter;
use App\Services\UiRules;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakeBeaconService;
use Tests\TestCase;

class DrawAdminPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const GENESIS = 1700000000;

    private FakeBeaconService $beacon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->beacon = new FakeBeaconService(self::GENESIS, 30);
        $this->app->instance(BeaconService::class, $this->beacon);
        Date::setTestNow(Date::createFromTimestamp(self::GENESIS + 999 * 30)); // current round 1000
    }

    private function admin(): User
    {
        return User::factory()->status('admin')->create();
    }

    /** @return array<string, string> */
    private function commitInput(array $override = []): array
    {
        return $override + [
            'title' => 'KO-Raster Demo',
            'entries' => "101\n102",
            'slots' => "S1\nS2",
            'rule' => 'Die gezogene Reihenfolge wird der Slot-Liste von oben nach unten zugeteilt.',
            'round' => '1200',
            'tid' => '',
        ];
    }

    #[Test]
    public function requires_admin_draw(): void
    {
        $this->get(route('draw.index'))
            ->assertRedirect(route('login', ['redirect_to' => '/draw']))
            ->assertSessionHas('error', 'Für diesen Bereich musst du angemeldet sein.');
        $this->post(route('draw.commit'), $this->commitInput())->assertRedirect();

        $this->actingAs(User::factory()->create())->get(route('draw.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('draw.commit'), $this->commitInput())->assertForbidden();

        $assist = User::factory()->status('assist')->create();
        $this->actingAs($assist)->get(route('draw.index'))->assertForbidden();
        $assist->setMeta('allow.admin.draw', 'true');
        $this->actingAs($assist->fresh())->get(route('draw.index'))->assertOk();

        $this->actingAs($this->admin())->get(route('draw.index'))->assertOk()->assertSee('Auslosungen');
        $this->assertSame(0, Draw::query()->count());
    }

    #[Test]
    public function the_admin_nav_shows_the_entry_for_admin_draw_users(): void
    {
        $this->actingAs($this->admin())->get(route('draw.index'))
            ->assertOk()
            ->assertSee('href="'.route('draw.index').'"', false)
            ->assertSee('aria-current="page"', false);
    }

    #[Test]
    public function a_real_login_session_gets_through(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['pw' => bcrypt('geheim-123')])->save();

        $this->post('/login', ['email' => $admin->email, 'password' => 'geheim-123']);
        $this->assertAuthenticatedAs($admin);

        $this->get(route('draw.index'))->assertOk()->assertSee('Bisherige Ziehungen');
        $this->post(route('draw.commit'), $this->commitInput())->assertRedirect(route('draw.index'));
        $this->assertSame(1, Draw::query()->count());
    }

    #[Test]
    public function lists_draws_newest_first_with_record_links(): void
    {
        $old = Draw::factory()->create(['title' => 'Alte Ziehung Demo']);
        $picking = Draw::factory()->token()->executed()->create(['title' => 'Token Ziehung Demo', 'pick_deadline' => '2026-11-08 20:00:00']);
        $discarded = Draw::factory()->discarded('Falscher Lostopf')->create(['title' => 'Verworfene Ziehung Demo']);
        $closed = Draw::factory()->executed()->create(['title' => 'Direkt ausgeführt Demo']);

        $response = $this->actingAs($this->admin())->get(route('draw.index'))->assertOk();

        $response->assertSeeInOrder(['Direkt ausgeführt Demo', 'Verworfene Ziehung Demo', 'Token Ziehung Demo', 'Alte Ziehung Demo']);
        foreach ([$old, $picking, $discarded, $closed] as $draw) {
            $response->assertSee('href="'.route('draw.record', ['slug' => $draw->slug]).'"', false);
        }
        $response->assertSee('Wahlphase offen')
            ->assertSee('festgeschrieben')
            ->assertSee('verworfen')
            ->assertSee('Falscher Lostopf')
            ->assertSee('Frist 08.11. 20:00')
            ->assertSee('ausgeführt')
            ->assertSee('ds-badge--attention', false)
            ->assertSee('ds-badge--problem', false)
            ->assertSee('ds-badge--good', false)
            ->assertSee('Token')
            ->assertSee('direkt');

        $response->assertSee('im Turnierbereich anwenden')->assertSee('keine weitere Aktion');
    }

    #[Test]
    public function empty_list_shows_the_zf2_notice(): void
    {
        $this->actingAs($this->admin())->get(route('draw.index'))->assertOk()->assertSee('Noch keine Ziehung angelegt.');
    }

    #[Test]
    public function suggested_round_uses_the_beacon(): void
    {
        $expected = $this->beacon->roundAt(now()->getTimestamp() + 3600);

        $this->actingAs($this->admin())->get(route('draw.index'))
            ->assertOk()
            ->assertSee('name="round"', false)
            ->assertSee('value="'.$expected.'"', false)
            ->assertSee('Manuelle Ziehung festschreiben – für Turnierauslosungen nicht nötig');
        $this->assertSame(1120, $expected);
    }

    #[Test]
    public function beacon_failure_shows_a_notice_not_an_error_page(): void
    {
        $this->beacon->unreachable = true;

        $this->actingAs($this->admin())->get(route('draw.index'))
            ->assertOk()
            ->assertSee('drand ist derzeit nicht erreichbar (no drand endpoint reachable). Die Rundennummer musst du manuell eintragen.');
    }

    #[Test]
    public function commit_post_creates_and_flashes(): void
    {
        $this->actingAs($this->admin())->post(route('draw.commit'), $this->commitInput())
            ->assertRedirect(route('draw.index'))
            ->assertSessionHas('success', 'Ziehung "KO-Raster Demo" festgeschrieben. Jetzt den Protokoll-Link ankündigen, BEVOR die Beacon-Runde 1200 veröffentlicht wird.');

        $draw = Draw::query()->sole();
        $this->assertSame('direct', $draw->mode);
        $this->assertSame('drand:round:1200', $draw->beacon_source);

        $this->get(route('draw.index'))->assertSee('KO-Raster Demo')->assertSee('festgeschrieben');
    }

    #[Test]
    public function commit_refusals_flash_every_error(): void
    {
        $response = $this->actingAs($this->admin())->post(route('draw.commit'), $this->commitInput(['title' => '', 'entries' => '101', 'slots' => "A\nA"]));

        $response->assertRedirect(route('draw.index'))->assertSessionHas('draw_errors', [
            'Titel fehlt.',
            'Mindestens zwei Lose notwendig.',
            'Lose (1) und Slots (2) müssen gleich viele sein.',
            'Die Slots enthalten Duplikate.',
        ]);
        $this->assertSame(0, Draw::query()->count());

        $page = $this->get(route('draw.index'))->assertOk();
        $page->assertSee('Titel fehlt.')->assertSee('Die Slots enthalten Duplikate.');
        $page->assertSee('<details class="ds-card ds-disclosure" open>', false); // the form stays open with the input
        $page->assertSee('Die gezogene Reihenfolge wird der Slot-Liste');
    }

    #[Test]
    public function commit_refuses_a_past_round_with_the_translated_beacon_message(): void
    {
        $this->actingAs($this->admin())->post(route('draw.commit'), $this->commitInput(['round' => '900']))
            ->assertSessionHas('draw_errors', ['drand-Runde 900 ist nicht zukünftig (aktuelle Runde: 1000).']);
    }

    #[Test]
    public function hostile_input_shapes_do_not_500(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('draw.commit'), ['title' => ['x'], 'entries' => ['a' => 'b'], 'slots' => [], 'rule' => ['r'], 'round' => ['1'], 'tid' => ['2']])
            ->assertRedirect(route('draw.index'))
            ->assertSessionHas('draw_errors');
        $this->actingAs($admin)->post(route('draw.commit'), $this->commitInput(['title' => str_repeat('x', 161)]))
            ->assertSessionHas('draw_errors', ['Der Titel darf höchstens 160 Zeichen lang sein.']);
        $this->assertSame(0, Draw::query()->count());
    }

    #[Test]
    public function get_commit_redirects(): void
    {
        $this->actingAs($this->admin())->get('/draw/commit')->assertRedirect(route('draw.index'));
    }

    #[Test]
    public function page_passes_the_ui_linter(): void
    {
        $this->assertSame([], (new UiLinter(app_path('Modules/Draw/resources/views'), UiRules::all()))->run());

        Draw::factory()->create();
        $html = $this->actingAs($this->admin())->get(route('draw.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('style="', (string) $html === '' ? '' : $this->main((string) $html));
        $this->assertStringNotContainsString('onclick=', $this->main((string) $html));
        $this->assertStringNotContainsString('onsubmit=', $this->main((string) $html));
    }

    private function main(string $html): string
    {
        $start = strpos($html, '<main');

        return $start === false ? '' : substr($html, $start);
    }
}
