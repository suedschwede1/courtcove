<?php

declare(strict_types=1);

namespace Tests\Feature\AdminMode;

use App\Models\Square;
use App\Models\User;
use App\Support\AdminMode;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The central rule (App\Support\AdminMode, User::can() vs User::hasPrivilege()). */
class AdminModeMechanismTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function every_privilege_is_classified_as_base_or_privileged(): void
    {
        $classified = array_merge(User::BASE_PRIVILEGES, User::privilegedPrivileges());
        sort($classified);
        $all = User::privileges();
        sort($all);

        $this->assertSame($all, $classified);
        $this->assertSame([], array_intersect(User::BASE_PRIVILEGES, User::privilegedPrivileges()));
    }

    #[Test]
    public function without_an_http_request_admin_mode_counts_as_active(): void
    {
        // Console commands, queue jobs, the scheduler and unit tests run without the HTTP marker.
        $admin = User::factory()->create(['status' => 'admin']);
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.booking']);

        $this->assertTrue($admin->can('admin.booking'));
        $this->assertTrue($assist->fresh()->can('admin.booking'));
        $this->assertTrue(Gate::forUser($admin)->allows('admin.members'));
    }

    #[Test]
    public function in_a_request_without_the_cookie_privileges_are_not_in_effect_but_stay_readable_raw(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $request = $this->markedRequest($admin, []);

        $this->assertFalse(AdminMode::active($admin, $request));
        $this->bindRequest($request);

        $this->assertFalse($admin->can('admin.booking'));
        $this->assertFalse($admin->can('calendar.see-past, admin.user'));
        $this->assertFalse($admin->can('administer'), 'role abilities through Gate::before are privileged too');
        $this->assertFalse(Gate::forUser($admin)->allows('admin.members'));
        $this->assertTrue($admin->hasPrivilege('admin.booking'));
        $this->assertTrue($admin->hasAnyPrivilege());
    }

    #[Test]
    public function the_cookie_activates_privileges_only_for_accounts_that_hold_one(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $member = User::factory()->create(['status' => 'enabled']);
        $bareAssist = User::factory()->create(['status' => 'assist']);

        $this->assertTrue(AdminMode::active($admin, $this->markedRequest($admin, ['admin_mode' => '1'])));
        $this->assertFalse(AdminMode::active($member, $this->markedRequest($member, ['admin_mode' => '1'])));
        $this->assertFalse(AdminMode::active($bareAssist, $this->markedRequest($bareAssist, ['admin_mode' => '1'])));
        $this->assertFalse(AdminMode::active($admin, $this->markedRequest($admin, ['admin_mode' => '0'])));
    }

    #[Test]
    public function checks_about_another_account_use_its_stored_rights(): void
    {
        $actor = User::factory()->create(['status' => 'enabled']);
        $other = User::factory()->create(['status' => 'admin']);
        $request = $this->markedRequest($actor, []);

        $this->assertTrue(AdminMode::active($other, $request));
    }

    #[Test]
    public function the_memo_is_per_request_and_does_not_leak_into_the_next_request(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $this->actingAsAdminMode($admin)->get('/admin/bookings')->assertOk();
        $this->actingAsWithoutAdminMode($admin)->get('/admin/bookings')->assertForbidden();
        $this->actingAsAdminMode($admin)->get('/admin/bookings')->assertOk();
    }

    #[Test]
    public function api_token_requests_count_as_admin_mode_active(): void
    {
        $this->withoutAdminMode();
        Sanctum::actingAs(User::factory()->create(['status' => 'admin']));

        // Authorization passes (no cookie needed); the invalid payload is rejected by validation.
        $this->postJson('/api/v1/events', ['sid' => 999])->assertUnprocessable();
    }

    #[Test]
    public function the_api_token_decision_point_can_make_token_requests_behave_like_members(): void
    {
        config(['booking.admin_mode.api_tokens_active' => false]);
        $this->withoutAdminMode();
        Sanctum::actingAs(User::factory()->create(['status' => 'admin']));

        $this->assertFalse(AdminMode::tokenRequestsCountAsActive());
        $this->postJson('/api/v1/events', ['sid' => 999])->assertForbidden();
    }

    #[Test]
    public function an_api_token_of_a_member_still_gets_nothing(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'enabled']));

        $this->postJson('/api/v1/events', ['sid' => Square::factory()->create()->sid])->assertForbidden();
    }

    /** @param array<string, string> $cookies */
    private function markedRequest(User $user, array $cookies): Request
    {
        $request = Request::create('/calendar', 'GET', [], $cookies);
        $request->attributes->set(AdminMode::HTTP_MARKER, true);
        $request->setUserResolver(static fn () => $user);

        return $request;
    }

    private function bindRequest(Request $request): void
    {
        $resolver = $request->getUserResolver();
        $this->app->instance('request', $request);
        // Rebinding the request installs the auth guard's resolver; keep the acting user of the test request.
        $request->setUserResolver($resolver);
    }
}
