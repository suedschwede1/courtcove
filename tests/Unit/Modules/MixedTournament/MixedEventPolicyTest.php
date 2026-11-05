<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MixedTournament;

use App\Models\User;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedEventGrant;
use App\Modules\MixedTournament\Policies\MixedEventPolicy;
use App\Modules\MixedTournament\Support\MixedRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every case of the old modulex/tests/mixed-permissions.php.
 */
class MixedEventPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function policy(): MixedEventPolicy
    {
        return $this->app->make(MixedEventPolicy::class);
    }

    private function grant(MixedEvent $event, User $user, string $role): void
    {
        MixedEventGrant::query()->create([
            'event_id' => $event->id, 'uid' => $user->uid, 'role' => $role, 'created' => '2027-04-01 09:00:00',
        ]);
    }

    private function canScore(?User $user, MixedEvent $event): bool
    {
        return $user !== null && Gate::forUser($user)->allows('score', $event);
    }

    private function canAdmin(?User $user, MixedEvent $event): bool
    {
        return $user !== null && Gate::forUser($user)->allows('administer', $event);
    }

    #[Test]
    public function anonymous_has_no_role(): void
    {
        $event = MixedEvent::factory()->create();

        $this->assertSame(MixedRole::None, $this->policy()->role(null, $event));
        $this->assertSame(MixedRole::None, $this->policy()->role(null));
        $this->assertFalse($this->canScore(null, $event));
        $this->assertFalse($this->canAdmin(null, $event));
    }

    #[Test]
    public function logged_in_user_without_grant_may_read(): void
    {
        $event = MixedEvent::factory()->create();
        $member = User::factory()->status('enabled')->create();

        $this->assertSame(MixedRole::Read, $this->policy()->role($member, $event));
        $this->assertSame(MixedRole::Read, $this->policy()->role($member));
        $this->assertTrue(Gate::forUser($member)->allows('view', $event));
        $this->assertFalse($this->canScore($member, $event));
    }

    #[Test]
    public function score_grant_scores_but_does_not_administer(): void
    {
        $event = MixedEvent::factory()->create();
        $member = User::factory()->status('enabled')->create();
        $this->grant($event, $member, 'score');

        $this->assertSame(MixedRole::Score, $this->policy()->role($member, $event));
        $this->assertTrue($this->canScore($member, $event));
        $this->assertFalse($this->canAdmin($member, $event));
    }

    #[Test]
    public function a_grant_does_not_leak_into_other_events_or_to_other_users(): void
    {
        $event = MixedEvent::factory()->create();
        $otherEvent = MixedEvent::factory()->create();
        $member = User::factory()->status('enabled')->create();
        $other = User::factory()->status('enabled')->create();
        $this->grant($event, $member, 'score');

        $this->assertSame(MixedRole::Read, $this->policy()->role($member, $otherEvent));
        $this->assertSame(MixedRole::Read, $this->policy()->role($other, $event));
    }

    #[Test]
    public function admin_grant_administers_and_includes_score(): void
    {
        $event = MixedEvent::factory()->create();
        $member = User::factory()->status('enabled')->create();
        $this->grant($event, $member, 'admin');

        $this->assertSame(MixedRole::Admin, $this->policy()->role($member, $event));
        $this->assertTrue($this->canAdmin($member, $event));
        $this->assertTrue($this->canScore($member, $event));
        $this->assertFalse(MixedEventPolicy::isGlobalAdmin($member));
    }

    #[Test]
    public function test_mode_makes_every_logged_in_user_event_admin_but_not_anonymous(): void
    {
        $testEvent = MixedEvent::factory()->create(['is_test' => 1]);
        $other = User::factory()->status('enabled')->create();

        $this->assertSame(MixedRole::Admin, $this->policy()->role($other, $testEvent));
        $this->assertSame(MixedRole::None, $this->policy()->role(null, $testEvent));
        $this->assertFalse(MixedEventPolicy::isGlobalAdmin($other));
    }

    #[Test]
    public function global_admin_is_admin_with_and_without_an_event(): void
    {
        $event = MixedEvent::factory()->create();
        $admin = User::factory()->status('admin')->create();

        $this->assertSame(MixedRole::Admin, $this->policy()->role($admin, $event));
        $this->assertSame(MixedRole::Admin, $this->policy()->role($admin));
        $this->assertTrue($this->canScore($admin, $event));
        $this->assertTrue(MixedEventPolicy::isGlobalAdmin($admin));
    }

    #[Test]
    public function assist_user_with_the_flag_is_admin_and_without_it_only_reads(): void
    {
        $event = MixedEvent::factory()->create();
        $flagged = User::factory()->status('assist')->create();
        $flagged->setMeta('allow.admin.mixed', 'true');
        $plain = User::factory()->status('assist')->create();

        $this->assertSame(MixedRole::Admin, $this->policy()->role($flagged->fresh(), $event));
        $this->assertTrue(MixedEventPolicy::isGlobalAdmin($flagged->fresh()));
        $this->assertSame(MixedRole::Read, $this->policy()->role($plain, $event));
        $this->assertFalse(MixedEventPolicy::isGlobalAdmin($plain));
        $this->assertFalse(MixedEventPolicy::isGlobalAdmin(null));
    }

    #[Test]
    public function a_revoked_grant_is_read_again(): void
    {
        $event = MixedEvent::factory()->create();
        $member = User::factory()->status('enabled')->create();
        $this->grant($event, $member, 'admin');
        MixedEventGrant::query()->where('event_id', $event->id)->delete();

        $this->assertSame(MixedRole::Read, $this->policy()->role($member, $event));
    }

    #[Test]
    public function role_ordering(): void
    {
        $this->assertTrue(MixedRole::Admin->atLeast(MixedRole::Score));
        $this->assertTrue(MixedRole::Score->atLeast(MixedRole::Read));
        $this->assertTrue(MixedRole::Read->atLeast(MixedRole::Read));
        $this->assertFalse(MixedRole::Read->atLeast(MixedRole::Score));
        $this->assertFalse(MixedRole::None->atLeast(MixedRole::Read));
    }
}
