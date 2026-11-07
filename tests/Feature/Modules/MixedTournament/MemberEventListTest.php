<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Models\User;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedRound;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MX-01: a read-only member event list (mixed.events) that stays reachable while a default event exists; member
 * back/list links point to it (never to the admin index, which needs admin mode); the default event stays a shortcut.
 */
class MemberEventListTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['status' => 'enabled']);
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="utf-8"?>'.$html);

        return new DOMXPath($doc);
    }

    #[Test]
    public function members_see_all_readable_events_while_a_default_event_is_set(): void
    {
        $default = MixedEvent::factory()->create(['title' => 'Herbst-Mixed', 'is_default' => 1]);
        $other = MixedEvent::factory()->create(['title' => 'Sommer-Mixed']);

        $response = $this->actingAsWithoutAdminMode($this->member())->get('/mixed-tournament/events')->assertOk();

        $response->assertSee('Herbst-Mixed')->assertSee('Sommer-Mixed')
            ->assertSee('href="'.route('mixed.event', ['event' => $default->id]).'"', false)
            ->assertSee('href="'.route('mixed.event', ['event' => $other->id]).'"', false)
            ->assertDontSee('data-admin-mode-required', false);

        $this->actingAsWithoutAdminMode($this->member())->get('/mixed-tournament/event/'.$other->id)->assertOk();
    }

    #[Test]
    public function the_module_start_still_opens_the_default_event_as_a_shortcut(): void
    {
        $default = MixedEvent::factory()->create(['is_default' => 1]);

        $this->actingAs($this->member())->get('/mixed-tournament')->assertRedirect(route('mixed.event', ['event' => $default->id]));
    }

    #[Test]
    public function the_sidebar_keeps_the_event_list_entry_with_a_default_event(): void
    {
        MixedEvent::factory()->create(['is_default' => 1]);

        $html = $this->actingAsWithoutAdminMode($this->member())->get('/mixed-tournament/events')->assertOk()->getContent();

        $links = $this->xpath($html)->query('//nav//a[@href="'.route('mixed.events').'"]');
        $this->assertGreaterThanOrEqual(1, $links->length);
        $this->assertSame('page', $links->item(0)?->getAttribute('aria-current'));
    }

    #[Test]
    public function member_back_links_point_to_the_member_list_not_the_admin_index(): void
    {
        $event = MixedEvent::factory()->create(['is_default' => 1]);
        $round = MixedRound::factory()->create(['event_id' => $event->id]);
        $member = $this->member();

        foreach (['/mixed-tournament/event/'.$event->id, '/mixed-tournament/event/'.$event->id.'/standings', '/mixed-tournament/round/'.$round->id, '/mixed-tournament/info'] as $url) {
            $xp = $this->xpath($this->actingAsWithoutAdminMode($member)->get($url)->assertOk()->getContent());
            $back = $xp->query('//main//a[normalize-space(.)="'.__('mixed::common.back_to_list').'"]');
            $this->assertSame(1, $back->length, $url);
            $this->assertSame(route('mixed.events'), $back->item(0)?->getAttribute('href'), $url);
        }
    }

    #[Test]
    public function the_member_list_offers_admin_entry_only_to_global_admins(): void
    {
        MixedEvent::factory()->create();

        $this->actingAsWithoutAdminMode($this->member())->get('/mixed-tournament/events')->assertOk()
            ->assertDontSee('href="'.route('mixed.admin.index').'"', false);
        $this->actingAsAdminMode(User::factory()->create(['status' => 'admin']))->get('/mixed-tournament/events')->assertOk()
            ->assertSee('href="'.route('mixed.admin.index').'"', false);
    }

    #[Test]
    public function an_empty_list_says_so(): void
    {
        $this->actingAs($this->member())->get('/mixed-tournament/events')->assertOk()->assertSee(__('mixed::common.no_events'));
    }

    #[Test]
    public function guests_are_sent_to_the_login(): void
    {
        MixedEvent::factory()->create(['is_default' => 1]);

        $response = $this->get('/mixed-tournament/events');

        $response->assertRedirect();
        $this->assertStringStartsWith(route('login'), (string) $response->headers->get('Location'));
    }

    private function underBasePath(): void
    {
        // Like BasePathUrlsTest: production serves the app under /booking. (One request per test: the test client
        // keeps the detected base path only for the first request.)
        $this->withServerVariables(['SCRIPT_NAME' => '/booking/index.php', 'SCRIPT_FILENAME' => base_path('public/index.php'), 'PHP_SELF' => '/booking/index.php']);
    }

    #[Test]
    public function the_back_link_honours_the_base_path(): void
    {
        $event = MixedEvent::factory()->create(['is_default' => 1]);
        $this->underBasePath();

        $html = $this->actingAs($this->member())->get('/booking/mixed-tournament/event/'.$event->id)->assertOk()->getContent();

        $this->assertStringContainsString('/booking/mixed-tournament/events"', $html);
    }

    #[Test]
    public function the_list_links_honour_the_base_path(): void
    {
        $event = MixedEvent::factory()->create(['is_default' => 1]);
        $this->underBasePath();

        $html = $this->actingAs($this->member())->get('/booking/mixed-tournament/events')->assertOk()->getContent();

        $this->assertStringContainsString('/booking/mixed-tournament/event/'.$event->id.'"', $html);
    }
}
