<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidEvent;
use App\Modules\Pyramid\Models\PyramidGrant;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Services\PyramidRepository;
use App\Modules\Pyramid\Support\EventText;
use App\Modules\Pyramid\Support\PyramidFormat;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\BuildsPyramidLadders;
use Tests\TestCase;

class PyramidRepositoryTest extends TestCase
{
    use BuildsPyramidLadders;
    use LazilyRefreshDatabase;

    private function repo(): PyramidRepository
    {
        return $this->app->make(PyramidRepository::class);
    }

    private function extra(PyramidLadder $ladder, string $label, string $status, array $attributes = []): PyramidPlayer
    {
        return PyramidPlayer::factory()->create(array_merge([
            'ladder_id' => $ladder->id,
            'label' => $label,
            'status' => $status,
            'position' => null,
        ], $attributes));
    }

    #[Test]
    public function ranking_excludes_waiting_and_unpositioned_players(): void
    {
        $ladder = $this->makeLadder(3);
        $this->extra($ladder, 'Wartend', 'waiting');
        $this->extra($ladder, 'Entfernt', 'removed');

        $ranking = $this->repo()->ranking($ladder->id);

        $this->assertSame(['Spieler 1', 'Spieler 2', 'Spieler 3'], $ranking->pluck('label')->all());
        $this->assertSame(3, $this->repo()->countActive($ladder->id));

        $this->playerAt($ladder, 2)->update(['status' => 'paused']);
        $this->assertSame(3, $this->repo()->countActive($ladder->id));
        $this->assertCount(3, $this->repo()->ranking($ladder->id));
    }

    #[Test]
    public function waiting_list_orders_by_joined_then_id(): void
    {
        $ladder = $this->makeLadder(1);
        $late = $this->extra($ladder, 'Spaet', 'waiting', ['joined' => '2026-03-02 10:00:00']);
        $a = $this->extra($ladder, 'Gleich A', 'waiting', ['joined' => '2026-03-01 10:00:00']);
        $b = $this->extra($ladder, 'Gleich B', 'entering', ['joined' => '2026-03-01 10:00:00']);

        $ids = $this->repo()->waitingList($ladder->id)->pluck('id')->all();

        $this->assertSame([$a->id, $b->id, $late->id], $ids);
    }

    #[Test]
    public function active_challenge_is_found_on_either_side(): void
    {
        $ladder = $this->makeLadder(3);
        $p1 = $this->playerAt($ladder, 1);
        $p2 = $this->playerAt($ladder, 2);
        $p3 = $this->playerAt($ladder, 3);
        $challenge = PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id,
            'challenger_id' => $p2->id,
            'opponent_id' => $p1->id,
            'status' => 'scheduled',
        ]);

        $this->assertSame($challenge->id, $this->repo()->activeChallengeFor($p1->id)?->id);
        $this->assertSame($challenge->id, $this->repo()->activeChallengeFor($p2->id)?->id);
        $this->assertNull($this->repo()->activeChallengeFor($p3->id));
    }

    #[Test]
    public function last_resolved_uses_resolved_then_id(): void
    {
        $ladder = $this->makeLadder(3);
        $p1 = $this->playerAt($ladder, 1);
        $p2 = $this->playerAt($ladder, 2);
        $p3 = $this->playerAt($ladder, 3);
        $first = PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id, 'challenger_id' => $p2->id, 'opponent_id' => $p1->id,
            'status' => 'played', 'resolved' => '2026-05-01 12:00:00',
        ]);
        $second = PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id, 'challenger_id' => $p1->id, 'opponent_id' => $p3->id,
            'status' => 'forfeited', 'resolved' => '2026-05-01 12:00:00',
        ]);
        PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id, 'challenger_id' => $p1->id, 'opponent_id' => $p2->id,
            'status' => 'declined', 'resolved' => '2026-06-01 12:00:00',
        ]);

        $this->assertSame($second->id, $this->repo()->lastResolvedChallengeFor($p1->id)?->id);
        $this->assertSame($first->id, $this->repo()->lastResolvedChallengeFor($p2->id)?->id);
        $this->assertSame(
            [$second->id, $first->id],
            $this->repo()->resolvedChallenges($ladder->id)->pluck('id')->all()
        );
        $this->assertSame([$second->id, $first->id], $this->repo()->matchHistory($p1->id)->pluck('id')->all());
    }

    #[Test]
    public function add_event_encodes_like_zf2(): void
    {
        $ladder = $this->makeLadder(1);

        $id = $this->repo()->addEvent($ladder->id, 'admin', [
            'message' => EventText::make(EventText::EXTENDED, [':date' => '2026-11-20', ':note' => 'Regen/Wind']),
        ]);

        $this->assertSame(
            '{"message":"Frist verl'.chr(92).'u00e4ngert bis 2026-11-20: Regen\/Wind"}',
            PyramidEvent::find($id)->payload
        );
        $this->assertNull(PyramidEvent::find($id)->challenge_id);
        $this->assertSame([$id], $this->repo()->feed($ladder->id)->pluck('id')->all());
    }

    #[Test]
    public function append_to_ranking_returns_next_position(): void
    {
        $ladder = $this->makeLadder(3);
        $waiting = $this->extra($ladder, 'Neu', 'waiting');

        $this->assertSame(4, $this->repo()->appendToRanking($waiting->id));
        $this->assertSame('active', $waiting->fresh()->status);
        $this->assertSame(4, (int) $waiting->fresh()->position);
        $this->assertRankingIntact($ladder->id);

        $empty = $this->makeLadder(0);
        $first = $this->extra($empty, 'Erster', 'waiting');
        $this->assertSame(1, $this->repo()->appendToRanking($first->id));

        $this->expectException(RuntimeException::class);
        $this->repo()->appendToRanking(999999);
    }

    #[Test]
    public function grants_are_idempotent(): void
    {
        $ladder = $this->makeLadder(1);

        $this->repo()->addGrant($ladder->id, 7);
        $this->repo()->addGrant($ladder->id, 7);

        $this->assertSame(1, PyramidGrant::query()->where('ladder_id', $ladder->id)->count());
        $this->assertSame('admin', $this->repo()->grant($ladder->id, 7)?->role);
        $this->assertNull($this->repo()->grant($ladder->id, null));

        $this->repo()->removeGrant($ladder->id, 7);
        $this->assertSame(0, PyramidGrant::query()->where('ladder_id', $ladder->id)->count());
    }

    #[Test]
    public function ladder_lookups_and_defaults(): void
    {
        $old = PyramidLadder::factory()->create(['season' => 2025]);
        $default = PyramidLadder::factory()->asDefault()->create(['season' => 2026]);
        $new = PyramidLadder::factory()->create(['season' => 2027]);

        $this->assertSame($default->id, $this->repo()->defaultLadder()?->id);
        $this->assertSame([$new->id, $default->id, $old->id], $this->repo()->ladders()->pluck('id')->all());
        $this->assertSame($old->id, $this->repo()->ladder($old->id)?->id);
        $this->assertNull($this->repo()->ladder(999999));
    }

    #[Test]
    public function labels_cover_ranking_and_waiting_list(): void
    {
        $ladder = $this->makeLadder(2);
        $entering = $this->extra($ladder, 'Freigegeben', 'entering');
        $this->extra($ladder, 'Entfernt', 'removed');

        $labels = $this->repo()->labels($ladder->id);

        $this->assertContains('Freigegeben', $labels);
        $this->assertContains('Spieler 2', $labels);
        $this->assertNotContains('Entfernt', $labels);
        $this->assertSame('Freigegeben', $labels[$entering->id]);
    }

    #[Test]
    public function player_by_uid_returns_null_for_falsy_uid(): void
    {
        $ladder = $this->makeLadder(1);
        $player = $this->extra($ladder, 'Mit Konto', 'waiting', ['uid' => 5]);

        $this->assertSame($player->id, $this->repo()->playerByUid($ladder->id, 5)?->id);
        $this->assertNull($this->repo()->playerByUid($ladder->id, null));
        $this->assertNull($this->repo()->playerByUid($ladder->id, 0));
    }

    #[Test]
    public function format_helpers(): void
    {
        app()->setLocale('en');
        $this->assertSame('–', PyramidFormat::itn(null));
        $this->assertSame('10.5', PyramidFormat::itn('10.5'));
        $this->assertSame('9.0', PyramidFormat::itn(9));
        $this->assertSame('24.12.2026', PyramidFormat::date('2026-12-24 18:30:00'));
        $this->assertSame('24.12.2026 18:30', PyramidFormat::dateTime('2026-12-24 18:30:00'));

        app()->setLocale('de');
        $this->assertSame('10,5', PyramidFormat::itn(10.5));
    }
}
