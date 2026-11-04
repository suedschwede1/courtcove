<?php

declare(strict_types=1);

namespace App\Modules\Draw\Database\Seeders;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Draw\Models\DrawToken;
use Database\Factories\Modules\Draw\Models\DrawFactory;
use Illuminate\Database\Seeder;

/**
 * Fictional draws in every lifecycle state for staging and demos. Fixed slugs and
 * seeds, so the commitments are identical on every run. Only touches the draw tables
 * and one example user.
 */
class DrawDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'draw-demo@example.test'],
            ['alias' => 'Demo Auslosung', 'status' => 'enabled', 'created' => now()]
        );

        $this->remove(['demo-committed', 'demo-direct', 'demo-discarded', 'demo-picking', 'demo-closed']);

        $this->base('demo-committed', 1, $user)->create();

        $this->base('demo-direct', 2, $user)->executed($this->beacon('demo-direct'))->create();

        $this->base('demo-discarded', 3, $user)
            ->executed($this->beacon('demo-discarded'))
            ->discarded('Demo: verworfen')
            ->create();

        $picking = $this->base('demo-picking', 4, $user, 'AC')
            ->executed($this->beacon('demo-picking'))
            ->create();
        $this->eligible($picking);
        $this->claim($picking, [1 => 'pick', 2 => 'pick']);

        $closed = $this->base('demo-closed', 5, $user, 'DG')
            ->executed($this->beacon('demo-closed'))
            ->closed()
            ->create();
        $this->eligible($closed);
        $this->claim($closed, [1 => 'pick', 2 => 'pick', 3 => 'pick', 4 => 'pick', 5 => 'fallback', 6 => 'fallback']);
    }

    private function base(string $slug, int $n, User $user, ?string $token = null): DrawFactory
    {
        // server_seed is set first: commitment and payload are derived from it afterwards
        $factory = Draw::factory()->state([
            'slug' => $slug,
            'title' => 'Demo '.$slug,
            'server_seed' => hash('sha256', 'demo-server-'.$slug),
            'beacon_source' => 'drand:round:'.(1000 + $n),
            'created_uid' => $user->uid,
        ]);

        if ($token !== null) {
            return $factory->token(6, $token);
        }

        return $factory->withPayload([
            'entries' => [9001, 9002, 9003, 9004],
            'slots' => ['A2', 'B2', 'C2', 'D2'],
            'rule' => 'Demoregel',
        ]);
    }

    private function beacon(string $slug): string
    {
        return hash('sha256', 'demo-beacon-'.$slug);
    }

    // Invented tpid/claimed_uid ids (9001+/9101+) assume no real rows with those ids exist; names are never joined.
    private function eligible(Draw $draw): void
    {
        for ($tpid = 9001; $tpid <= 9006; $tpid++) {
            DrawEligible::create(['did' => $draw->did, 'tpid' => $tpid]);
        }
    }

    /**
     * @param  array<int, string>  $byPosition  position => pick|fallback
     */
    private function claim(Draw $draw, array $byPosition): void
    {
        // Reset whatever the factory pre-claimed, then claim exactly the given positions.
        DrawToken::query()->where('did', $draw->did)->update([
            'claimed_tpid' => null, 'claimed_uid' => null, 'claimed' => null, 'assigned_by' => null,
        ]);

        foreach ($byPosition as $position => $how) {
            DrawToken::query()->where('did', $draw->did)->where('position', $position)->update([
                'claimed_tpid' => 9000 + $position,
                'claimed_uid' => 9100 + $position,
                'claimed' => now()->format('Y-m-d H:i:s'),
                'assigned_by' => $how,
            ]);
        }
    }

    /**
     * @param  list<string>  $slugs
     */
    private function remove(array $slugs): void
    {
        $ids = Draw::query()->whereIn('slug', $slugs)->pluck('did');

        DrawToken::query()->whereIn('did', $ids)->delete();
        DrawEligible::query()->whereIn('did', $ids)->delete();
        Draw::query()->whereIn('did', $ids)->delete();
    }
}
