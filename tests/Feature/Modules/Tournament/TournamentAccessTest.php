<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Models\UserMeta;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Support\AccessContext;
use App\Modules\Tournament\Support\TournamentAccess;
use App\Modules\TournamentBase\Support\TestModeSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Port of the old modulex/tests/access.php (1:1) plus the selection and stale-simulation rules of
 * Controller/Plugin/TournamentAccess.php.
 */
class TournamentAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Tournament $tournament;

    private Tournament $other;

    private TournamentPlayer $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tournament = Tournament::factory()->active()->create();
        $this->other = Tournament::factory()->create(['season' => 2026]);
        TournamentPlayer::factory()->create(['tid' => $this->tournament->tid, 'seed' => 1]);
        $this->player = TournamentPlayer::factory()->forUser($this->user('enabled'))->create(['tid' => $this->tournament->tid, 'seed' => 2, 'label' => 'Demo Teilnehmer']);
    }

    private function user(string $status): User
    {
        return User::factory()->create(['status' => $status]);
    }

    private function assist(bool $allowed): User
    {
        $assist = $this->user('assist');
        if ($allowed) {
            UserMeta::create(['uid' => $assist->uid, 'key' => 'allow.admin.tournament', 'value' => 'true']);
        }

        return $assist;
    }

    private function access(): TournamentAccess
    {
        return $this->app->make(TournamentAccess::class);
    }

    private function modeSession(): TestModeSession
    {
        return $this->app->make(TestModeSession::class);
    }

    private function context(User $user, ?Tournament $t = null): AccessContext
    {
        return $this->access()->context($user, $t ?? $this->tournament);
    }

    #[Test]
    public function access_matrix_matches_access_php(): void
    {
        $member = $this->context($this->user('enabled'));
        $this->assertSame([TournamentAccess::MEMBER, null, null, true], [$member->level, $member->player, $member->tpid, $member->canWrite]);
        $this->assertFalse($member->isAdmin());

        $participantUser = User::query()->findOrFail($this->player->uid);
        $participant = $this->context($participantUser);
        $this->assertSame(TournamentAccess::PARTICIPANT, $participant->level);
        $this->assertSame((int) $this->player->tpid, $participant->tpid);
        $this->assertSame('Demo Teilnehmer', $participant->player['label']);
        $this->assertTrue($participant->canWrite);
        $this->assertSame(TournamentAccess::MEMBER, $this->context($participantUser, $this->other)->level, 'participant only in his tournament');

        $admin = $this->context($this->user('admin'));
        $this->assertSame([TournamentAccess::ADMIN, true, null], [$admin->level, $admin->canWrite, $admin->simulation]);
        $this->assertTrue($admin->isAdmin());

        $this->assertSame(TournamentAccess::ADMIN, $this->context($this->assist(true))->level);
        $this->assertSame(TournamentAccess::MEMBER, $this->context($this->assist(false))->level);

        $this->assertSame(TournamentAccess::ADMIN, $this->access()->level($this->user('admin'), $this->tournament));
        $this->assertTrue($this->access()->canWrite($this->user('enabled'), $this->tournament));
    }

    #[Test]
    public function an_admin_who_plays_keeps_the_admin_level_and_his_player(): void
    {
        $admin = $this->user('admin');
        TournamentPlayer::factory()->forUser($admin)->create(['tid' => $this->tournament->tid, 'seed' => 3]);

        $context = $this->context($admin);

        $this->assertSame(TournamentAccess::ADMIN, $context->level);
        $this->assertNotNull($context->tpid);
    }

    #[Test]
    public function simulation_never_raises_the_level(): void
    {
        $participantUser = User::query()->findOrFail($this->player->uid);
        try {
            $this->modeSession()->simulate($participantUser, 'member');
            $this->fail('a participant must not start the test mode');
        } catch (AuthorizationException) {
        }
        $this->assertNull($this->context($participantUser)->simulation);

        $admin = $this->user('admin');
        $this->modeSession()->simulate($admin, 'member');
        $member = $this->context($admin);
        $this->assertSame([TournamentAccess::MEMBER, false, null, null], [$member->level, $member->canWrite, $member->player, $member->tpid]);
        $this->assertSame(['mode' => 'member', 'label' => 'einfaches Mitglied ohne Teilnahme', 'realLevel' => TournamentAccess::ADMIN], $member->simulation);
        $this->assertFalse($member->isAdmin());

        $this->modeSession()->simulate($admin, 'player', (int) $this->player->tpid);
        $player = $this->context($admin);
        $this->assertSame([TournamentAccess::PARTICIPANT, false, (int) $this->player->tpid], [$player->level, $player->canWrite, $player->tpid]);
        $this->assertSame(['mode' => 'player', 'label' => 'Demo Teilnehmer', 'tpid' => (int) $this->player->tpid, 'realLevel' => TournamentAccess::ADMIN], $player->simulation);
        $this->assertSame('Demo Teilnehmer', $player->player['label']);
    }

    #[Test]
    public function stopping_simulation_restores_admin(): void
    {
        $admin = $this->user('admin');
        $this->modeSession()->simulate($admin, 'member');
        $this->modeSession()->stop();

        $context = $this->context($admin);

        $this->assertSame(TournamentAccess::ADMIN, $context->level);
        $this->assertTrue($context->canWrite);
        $this->assertNull($context->simulation);
    }

    #[Test]
    public function switching_tournament_stops_simulation(): void
    {
        $admin = $this->user('admin');
        $this->modeSession()->selectTid($admin, (int) $this->tournament->tid);
        $this->assertSame((int) $this->tournament->tid, $this->context($admin)->selectedTid);

        $this->modeSession()->simulate($admin, 'player', (int) $this->player->tpid);
        $this->modeSession()->selectTid($admin, (int) $this->other->tid);

        $context = $this->context($admin);
        $this->assertSame((int) $this->other->tid, $context->selectedTid);
        $this->assertNull($context->simulation);
        $this->assertSame(TournamentAccess::ADMIN, $context->level);
    }

    #[Test]
    public function members_never_inherit_the_admin_selection(): void
    {
        $this->modeSession()->selectTid($this->user('admin'), (int) $this->other->tid);
        $member = $this->user('enabled');

        $this->assertNull($this->context($member)->selectedTid);
        $this->assertSame((int) $this->tournament->tid, (int) $this->access()->resolveTournament($member)->tid);

        $this->expectException(AuthorizationException::class);
        $this->modeSession()->selectTid($member, (int) $this->tournament->tid);
    }

    #[Test]
    public function stale_simulated_player_is_dropped(): void
    {
        $admin = $this->user('admin');
        $foreign = TournamentPlayer::factory()->create(['tid' => $this->other->tid, 'seed' => 1]);
        $this->modeSession()->simulate($admin, 'player', (int) $foreign->tpid);

        $context = $this->context($admin);

        $this->assertNull($context->simulation);
        $this->assertSame(TournamentAccess::ADMIN, $context->level);
        $this->assertTrue($context->canWrite);
        $this->assertNull($this->modeSession()->simulation($admin), 'session cleared');
    }

    #[Test]
    public function resolve_tournament_falls_back_to_current_when_selection_vanished(): void
    {
        $admin = $this->user('admin');

        $this->assertSame((int) $this->tournament->tid, (int) $this->access()->resolveTournament($admin)->tid, 'no selection: current');

        $this->modeSession()->selectTid($admin, (int) $this->other->tid);
        $this->assertSame((int) $this->other->tid, (int) $this->access()->resolveTournament($admin)->tid, 'selection wins');

        $this->modeSession()->simulate($admin, 'member');
        $this->other->delete();
        $this->app->forgetScopedInstances(); // next request: the manager cache starts empty

        $this->assertSame((int) $this->tournament->tid, (int) $this->access()->resolveTournament($admin)->tid);
        $this->assertNull($this->modeSession()->selectedTid($admin), 'selection cleared');
        $this->assertNull($this->modeSession()->simulation($admin), 'the cleared selection also ends the simulation (ZF2 selectTid(null))');
    }
}
