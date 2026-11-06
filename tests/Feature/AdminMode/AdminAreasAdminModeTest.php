<?php

declare(strict_types=1);

namespace Tests\Feature\AdminMode;

use App\Models\User;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedEventGrant;
use App\Modules\Pyramid\Models\PyramidGrant;
use App\Modules\Pyramid\Models\PyramidLadder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Administration areas: without admin mode a privileged account gets the "Switch on admin mode" page
 * (403 with the switch form) instead of the plain 403; with admin mode the page opens. Accounts without
 * the area's privilege keep the plain 403, also with the cookie. Per-ladder / per-event grants are member
 * rights and keep working without admin mode.
 */
class AdminAreasAdminModeTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string}> */
    public static function areas(): array
    {
        return [
            'admin dashboard' => ['/admin'],
            'admin users' => ['/admin/users'],
            'admin bookings' => ['/admin/bookings'],
            'admin config' => ['/admin/config'],
            'members dashboard' => ['/members/dashboard'],
            'members list' => ['/members'],
            'pyramid admin' => ['/pyramide/admin'],
            'mixed admin' => ['/mixed-tournament/admin'],
            'tournament admin' => ['/tournament/admin'],
            'draw admin' => ['/draw'],
        ];
    }

    /** @return array<string, array{bool}> */
    public static function roleStorages(): array
    {
        return ['merged status' => [false], 'split role column' => [true]];
    }

    private function admin(bool $split = false): User
    {
        config(['users.split_role' => $split]);

        return $split
            ? User::factory()->create(['status' => 'enabled', 'role' => 'admin'])
            : User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    #[DataProvider('areas')]
    public function without_admin_mode_an_admin_gets_the_switch_page(string $url): void
    {
        $admin = $this->admin();

        $response = $this->actingAsWithoutAdminMode($admin)->get($url.'?tab=x');

        $response->assertForbidden()
            ->assertSee('data-admin-mode-required', false)
            ->assertSee(__('booking.admin_mode.required_title'))
            ->assertSee('action="'.route('admin-mode.set', ['state' => 'on']).'"', false)
            ->assertSee('name="redirect" value="'.$url.'?tab=x"', false)
            ->assertDontSee('<aside id="admin-nav"', false);
    }

    #[Test]
    #[DataProvider('areas')]
    public function with_admin_mode_the_area_opens(string $url): void
    {
        $this->actingAsAdminMode($this->admin())->get($url)->assertOk()->assertDontSee('data-admin-mode-required', false);
    }

    #[Test]
    #[DataProvider('roleStorages')]
    public function both_role_storages_follow_the_mode(bool $split): void
    {
        $admin = $this->admin($split);

        $this->actingAsWithoutAdminMode($admin)->get('/admin/users')->assertForbidden()->assertSee('data-admin-mode-required', false);
        $this->actingAsAdminMode($admin)->get('/admin/users')->assertOk();
    }

    #[Test]
    #[DataProvider('areas')]
    public function a_member_with_the_cookie_gets_the_plain_403_and_no_switch(string $url): void
    {
        $member = User::factory()->create(['status' => 'enabled']);

        $response = $this->actingAsAdminMode($member)->get($url);

        if ($url === '/mixed-tournament/admin') {
            // The mixed overview answers every member (event grants); it shows no admin entries.
            $response->assertOk()->assertDontSee('data-admin-mode-required', false);

            return;
        }
        $response->assertForbidden()->assertDontSee('data-admin-mode-required', false);
    }

    #[Test]
    public function an_assist_only_gets_the_switch_page_for_areas_of_its_privileges(): void
    {
        config(['users.split_role' => false]);
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.members']);

        $this->actingAsWithoutAdminMode($assist)->get('/members/dashboard')->assertForbidden()->assertSee('data-admin-mode-required', false);
        $this->actingAsWithoutAdminMode($assist)->get('/admin/users')->assertForbidden()->assertDontSee('data-admin-mode-required', false);
        $this->actingAsWithoutAdminMode($assist)->get('/pyramide/admin')->assertForbidden()->assertDontSee('data-admin-mode-required', false);

        $this->actingAsAdminMode($assist)->get('/members/dashboard')->assertOk();
        $this->actingAsAdminMode($assist)->get('/admin/users')->assertForbidden();
    }

    #[Test]
    public function the_switch_button_turns_the_mode_on_and_returns_to_the_page(): void
    {
        $admin = $this->admin();

        $this->actingAsWithoutAdminMode($admin)
            ->post('/admin-mode/on', ['redirect' => '/admin/users?status=enabled'])
            ->assertRedirect('/admin/users?status=enabled')
            ->assertPlainCookie('admin_mode', '1');

        // The browser now sends the session cookie.
        $this->actingAsAdminMode($admin)->get('/admin/users?status=enabled')->assertOk();
    }

    #[Test]
    public function a_denied_post_returns_to_the_referring_page_of_the_same_host_only(): void
    {
        $admin = $this->admin();

        $this->actingAsWithoutAdminMode($admin)->from(url('/admin/users'))->post('/admin/users/bulk', [])
            ->assertForbidden()->assertSee('name="redirect" value="/admin/users"', false);

        $this->actingAsWithoutAdminMode($admin)->from('https://evil.example/phish')->post('/admin/users/bulk', [])
            ->assertForbidden()->assertSee('name="redirect" value="/calendar"', false);
    }

    #[Test]
    public function json_requests_get_a_403_json_instead_of_the_page(): void
    {
        $admin = $this->admin();

        $this->actingAsWithoutAdminMode($admin)->getJson('/members/dashboard')
            ->assertForbidden()
            ->assertExactJson(['message' => __('booking.admin_mode.required_json'), 'admin_mode_required' => true]);

        $this->actingAsWithoutAdminMode($admin)->postJson('/admin/users/bulk', [])
            ->assertForbidden()->assertJsonPath('admin_mode_required', true);

        // fetch() with X-Requested-With and its default Accept */* (dialogs, iframes loading via AJAX).
        $this->actingAsWithoutAdminMode($admin)->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => '*/*'])->get('/admin/users')
            ->assertForbidden()->assertJsonPath('admin_mode_required', true);

        $member = User::factory()->create(['status' => 'enabled']);
        $this->actingAsAdminMode($member)->getJson('/members/dashboard')->assertForbidden()->assertJsonMissingPath('admin_mode_required');
    }

    // --- grants stay member rights -------------------------------------------------------------------

    #[Test]
    public function a_pyramid_ladder_grant_works_without_admin_mode(): void
    {
        $ladder = PyramidLadder::factory()->create();
        $holder = User::factory()->create(['status' => 'enabled']);
        PyramidGrant::query()->create(['ladder_id' => $ladder->id, 'uid' => $holder->uid, 'role' => 'admin', 'created' => '2026-01-01 00:00:00']);

        $this->actingAsWithoutAdminMode($holder)->get('/pyramide/admin/ladder/'.$ladder->id)->assertOk();

        // A global pyramid admin who also holds the ladder grant keeps the ladder without admin mode.
        $admin = $this->admin();
        PyramidGrant::query()->create(['ladder_id' => $ladder->id, 'uid' => $admin->uid, 'role' => 'admin', 'created' => '2026-01-01 00:00:00']);
        $this->actingAsWithoutAdminMode($admin)->get('/pyramide/admin/ladder/'.$ladder->id)->assertOk();

        // Without the grant the global right needs admin mode.
        $other = PyramidLadder::factory()->create();
        $this->actingAsWithoutAdminMode($admin)->get('/pyramide/admin/ladder/'.$other->id)->assertForbidden()->assertSee('data-admin-mode-required', false);
        $this->actingAsAdminMode($admin)->get('/pyramide/admin/ladder/'.$other->id)->assertOk();
    }

    #[Test]
    public function a_mixed_event_grant_works_without_admin_mode(): void
    {
        $event = MixedEvent::factory()->create(['is_test' => 0]);
        $holder = User::factory()->create(['status' => 'enabled']);
        MixedEventGrant::factory()->create(['event_id' => $event->id, 'uid' => $holder->uid, 'role' => 'admin']);

        $this->actingAsWithoutAdminMode($holder)->get('/mixed-tournament/admin/event/'.$event->id)->assertOk();

        $admin = $this->admin();
        $this->actingAsWithoutAdminMode($admin)->get('/mixed-tournament/admin/event/'.$event->id)
            ->assertForbidden()->assertSee('data-admin-mode-required', false);
        $this->actingAsAdminMode($admin)->get('/mixed-tournament/admin/event/'.$event->id)->assertOk();
    }

    #[Test]
    public function a_mixed_test_mode_event_still_makes_every_member_its_admin(): void
    {
        $event = MixedEvent::factory()->create(['is_test' => 1]);
        $member = User::factory()->create(['status' => 'enabled']);

        $this->actingAsWithoutAdminMode($member)->get('/mixed-tournament/admin/event/'.$event->id)->assertOk();
    }

    // --- header and menu -----------------------------------------------------------------------------

    #[Test]
    public function the_menu_entries_stay_visible_without_admin_mode_and_lead_to_the_switch_page(): void
    {
        $admin = $this->admin();

        $html = $this->actingAsWithoutAdminMode($admin)->get('/calendar')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('admin.dashboard').'"', $html);
        $this->assertStringContainsString('href="'.route('members.dashboard').'"', $html);
        $this->assertStringContainsString('action="'.route('admin-mode.set', ['state' => 'on']).'"', $html);

        $this->actingAsWithoutAdminMode($admin)->get(route('admin.dashboard'))->assertSee('data-admin-mode-required', false);
    }

    #[Test]
    public function members_see_neither_the_admin_entries_nor_the_switch(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);

        $html = $this->actingAsAdminMode($member)->get('/calendar')->assertOk()->getContent();
        $this->assertStringNotContainsString('href="'.route('admin.dashboard').'"', $html);
        $this->assertStringNotContainsString('href="'.route('members.dashboard').'"', $html);
        $this->assertStringNotContainsString('role="switch"', $html);
    }

    #[Test]
    public function an_assist_without_booking_rights_sees_the_switch_and_only_its_entries(): void
    {
        config(['users.split_role' => false]);
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.members']);

        $html = $this->actingAsWithoutAdminMode($assist)->get('/calendar')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('members.dashboard').'"', $html);
        $this->assertStringNotContainsString('href="'.route('admin.dashboard').'"', $html);
        $this->assertStringContainsString('role="switch"', $html);
    }
}
