<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Models\PyramidGrant;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Policies\PyramidLadderPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PyramidLadderPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function policy(): PyramidLadderPolicy
    {
        return $this->app->make(PyramidLadderPolicy::class);
    }

    private function grantHolder(PyramidLadder $ladder): User
    {
        $user = User::factory()->create();
        PyramidGrant::query()->create(['ladder_id' => $ladder->id, 'uid' => $user->uid, 'role' => 'admin', 'created' => '2026-01-01 00:00:00']);

        return $user;
    }

    #[Test]
    public function guest_is_none(): void
    {
        $ladder = PyramidLadder::factory()->create();

        $this->assertSame('none', $this->policy()->roleFor(null, $ladder));
        $this->assertSame('none', $this->policy()->roleFor(null));
    }

    #[Test]
    public function logged_in_reader_is_read(): void
    {
        $ladder = PyramidLadder::factory()->create();
        $user = User::factory()->create();

        $this->assertSame('read', $this->policy()->roleFor($user, $ladder));
        $this->assertFalse(Gate::forUser($user)->allows('administer', $ladder));
        $this->assertFalse(Gate::forUser($user)->allows('administer', PyramidLadder::class));
    }

    #[Test]
    public function global_right_admins_every_ladder_and_needs_none(): void
    {
        $a = PyramidLadder::factory()->create();
        $b = PyramidLadder::factory()->create();
        $admin = User::factory()->status('admin')->create();
        $assist = User::factory()->status('assist')->create();
        $assist->setMeta('allow.admin.pyramid', 'true');

        foreach ([$admin, $assist] as $user) {
            $this->assertSame('admin', $this->policy()->roleFor($user, $a));
            $this->assertTrue(Gate::forUser($user)->allows('administer', $a));
            $this->assertTrue(Gate::forUser($user)->allows('administer', $b));
            $this->assertTrue(Gate::forUser($user)->allows('administer', PyramidLadder::class));
        }
    }

    #[Test]
    public function assist_without_the_pyramid_right_is_read(): void
    {
        $ladder = PyramidLadder::factory()->create();
        $assist = User::factory()->status('assist')->create();
        $assist->setMeta('allow.admin.tournament', 'true');

        $this->assertSame('read', $this->policy()->roleFor($assist, $ladder));
    }

    #[Test]
    public function grant_admins_only_its_ladder(): void
    {
        $a = PyramidLadder::factory()->create();
        $b = PyramidLadder::factory()->create();
        $user = $this->grantHolder($a);

        $this->assertSame('admin', $this->policy()->roleFor($user, $a));
        $this->assertSame('read', $this->policy()->roleFor($user, $b));
        $this->assertTrue(Gate::forUser($user)->allows('administer', $a));
        $this->assertFalse(Gate::forUser($user)->allows('administer', $b));
        $this->assertFalse(Gate::forUser($user)->allows('administer', PyramidLadder::class));
    }

    #[Test]
    public function a_read_grant_row_cannot_exist_so_the_policy_never_sees_one(): void
    {
        // The legacy column is ENUM('admin'), so a 'read' grant is rejected by the schema itself; the policy's
        // role check is defence in depth and a user without a row stays 'read'.
        $ladder = PyramidLadder::factory()->create();
        $user = User::factory()->create();

        try {
            PyramidGrant::query()->create(['ladder_id' => $ladder->id, 'uid' => $user->uid, 'role' => 'read', 'created' => '2026-01-01 00:00:00']);
            $this->fail('The schema must reject a non-admin grant role');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('read', $this->policy()->roleFor($user, $ladder));
        $this->assertFalse(Gate::forUser($user)->allows('administer', $ladder));
    }

    #[Test]
    public function guest_is_denied_through_the_gate(): void
    {
        $ladder = PyramidLadder::factory()->create();

        $this->assertFalse(Gate::allows('administer', $ladder));
        $this->assertFalse(Gate::allows('administer', PyramidLadder::class));
    }

    #[Test]
    public function user_can_is_not_the_policy(): void
    {
        $ladderA = PyramidLadder::factory()->create();
        $grantHolder = $this->grantHolder($ladderA);

        $this->assertNotContains($grantHolder->status, ['admin', 'assist']);
        $this->assertFalse($grantHolder->can('administer', $ladderA)); // privilege check, NOT the policy
        $this->assertTrue(Gate::forUser($grantHolder)->allows('administer', $ladderA));
    }

    #[Test]
    public function policy_has_no_contact_method(): void
    {
        $this->assertFalse(method_exists(PyramidLadderPolicy::class, 'canSeeContact'));
    }
}
