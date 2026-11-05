<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Database\Seeders;

use App\Models\User;
use App\Modules\MixedTournament\Domain\EventService;
use App\Modules\MixedTournament\Domain\ParticipantService;
use App\Modules\MixedTournament\Domain\ProposalGenerator;
use App\Modules\MixedTournament\Domain\RoundService;
use App\Modules\MixedTournament\Models\MixedMatch;
use App\Modules\MixedTournament\Models\MixedMember;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Fictional Mixed data for staging and demos: a member roster of 30 invented players, an Americano
 * event (one completed round with an extra group, one half-scored confirmed round, avoid pair, paused
 * player, grants), a Mexicano event (one completed round, a proposed round with two sitters) and a
 * test event. Deterministic (fixed seed for the group generator) and only touches the mixed tables
 * plus two example.test demo users; it never reads other tables.
 *
 * Not idempotent without a truncate: run it through `phase2:scrub-staging`, which empties the tables first.
 */
class MixedDemoSeeder extends Seeder
{
    private const SEED = 2027;

    private const FORMAT = 'Y-m-d H:i:s';

    /** Fictional first names with their gender (f = D, m = H), alternating so every slice of the roster has both. */
    private const FIRST_NAMES = [
        'Anna' => 'f', 'Bruno' => 'm', 'Clara' => 'f', 'Dario' => 'm', 'Emma' => 'f',
        'Emil' => 'm', 'Frieda' => 'f', 'Felix' => 'm', 'Greta' => 'f', 'Gustav' => 'm',
        'Hanna' => 'f', 'Hugo' => 'm', 'Ida' => 'f', 'Ivo' => 'm', 'Julia' => 'f',
        'Jonas' => 'm', 'Klara' => 'f', 'Kilian' => 'm', 'Lena' => 'f', 'Leo' => 'm',
        'Marta' => 'f', 'Max' => 'm', 'Nora' => 'f', 'Noah' => 'm', 'Olga' => 'f',
        'Oskar' => 'm', 'Paula' => 'f', 'Paul' => 'm', 'Rosa' => 'f', 'Rudi' => 'm',
    ];

    /** The scores handed out one after the other (Americano rounds always add up to four games). */
    private const RESULTS = ['3:1', '2:2', '4:0', '1:3'];

    public function run(): void
    {
        $now = CarbonImmutable::now();

        $admin = User::updateOrCreate(
            ['email' => 'mixed-demo-admin@example.test'],
            ['alias' => 'Demo Turnierleitung', 'status' => 'enabled', 'created' => $now->format(self::FORMAT)]
        );
        $score = User::updateOrCreate(
            ['email' => 'mixed-demo-score@example.test'],
            ['alias' => 'Demo Ergebnisdienst', 'status' => 'enabled', 'created' => $now->format(self::FORMAT)]
        );

        $labels = $this->seedRoster();

        // The generator draws from the container's Randomizer: bind a fixed one for the demo data only.
        $app = $this->container;
        // Production binds a factory (new Randomizer per resolve): that binding is never touched, only an
        // instance entry is layered on top and removed again. A previously registered instance (the same
        // object on every resolve) is put back afterwards.
        $previous = null;
        if ($app->bound(Randomizer::class)) {
            $probe = $app->make(Randomizer::class);
            $previous = $probe === $app->make(Randomizer::class) ? $probe : null;
        }
        $app->instance(Randomizer::class, new Randomizer(new Mt19937(self::SEED)));

        try {
            $this->seedAmericano($labels, $admin, $score);
            $this->seedMexicano($labels);
            $this->seedTestEvent($labels);
        } finally {
            $app->forgetInstance(Randomizer::class);
            if ($previous !== null) {
                $app->instance(Randomizer::class, $previous);
            }
        }
    }

    /**
     * @return list<string> the 30 roster labels
     */
    private function seedRoster(): array
    {
        $labels = [];

        $i = 0;
        foreach (self::FIRST_NAMES as $first => $gender) {
            $label = 'Beispiel '.$first;
            $labels[] = $label;

            MixedMember::query()->create([
                'label' => $label,
                'gender' => $gender,
                'itn' => number_format(3.0 + (($i * 13) % 14) * 0.5, 1, '.', ''),
                'birth_date' => sprintf('%04d-%02d-%02d', 1960 + (($i * 7) % 49), 1 + ($i * 5) % 12, 1 + ($i * 11) % 28),
            ]);
            $i++;
        }

        return $labels;
    }

    /**
     * @param  list<string>  $labels
     */
    private function seedAmericano(array $labels, User $admin, User $score): void
    {
        $events = app(EventService::class);
        $participants = app(ParticipantService::class);
        $rounds = app(RoundService::class);
        $generator = app(ProposalGenerator::class);

        $event = $events->create('Demo-Mixed', '2027-04-17', 'americano');
        $events->setDefault($event);
        $events->setCourtCount($event, 3);
        $event->update(['status' => 'running']);

        $added = [];
        foreach (array_slice($labels, 0, 13) as $i => $label) {
            // The fifth player is excluded from extra matches (filler duty).
            $added[] = $participants->add($event, ['label' => $label, 'no_extra_match' => $i === 4]);
        }

        $participants->addAvoidPair($event, (int) $added[0]->id, (int) $added[1]->id);
        $events->setGrant($event, (int) $admin->uid, 'admin');
        $events->setGrant($event, (int) $score->uid, 'score');

        // Round 1: 13 players in groups of four leave one over, so an extra group with fillers is formed.
        $first = $generator->createAndPropose($event, '4_only', 'itn_balanced', array_map(fn (MixedParticipant $p): int => (int) $p->id, $added));
        $rounds->confirm($first);
        $this->score($rounds, $first, null);
        $rounds->complete($first);

        // The last player pauses from the second round on.
        $participants->setStatus($added[12], 'paused');

        $active = array_map(fn (MixedParticipant $p): int => (int) $p->id, array_slice($added, 0, 12));
        $second = $generator->createAndPropose($event, '4_and_5', 'random', $active);
        $rounds->confirm($second);
        $this->score($rounds, $second, 2);
    }

    /**
     * @param  list<string>  $labels
     */
    private function seedMexicano(array $labels): void
    {
        $events = app(EventService::class);
        $participants = app(ParticipantService::class);
        $rounds = app(RoundService::class);
        $generator = app(ProposalGenerator::class);

        $event = $events->create('Demo-Mexicano', '2027-05-08', 'mexicano');
        $events->setCourtCount($event, 2);
        $event->update(['status' => 'running']);

        $ids = [];
        foreach (array_slice($labels, 14, 14) as $label) {
            $ids[] = (int) $participants->add($event, ['label' => $label])->id;
        }

        // Round 1 starts at random (no standings yet) and is played; round 2 pairs by standings with two sitters.
        $first = $generator->createAndPropose($event, '4_only', 'random', $ids);
        $rounds->confirm($first);
        $this->score($rounds, $first, null);
        $rounds->complete($first);

        $generator->createAndPropose($event, '4_only', 'random', $ids);
    }

    /**
     * @param  list<string>  $labels
     */
    private function seedTestEvent(array $labels): void
    {
        $events = app(EventService::class);
        $participants = app(ParticipantService::class);

        $event = $events->create('Demo-Test', '2027-06-05', 'americano');
        $events->setCourtCount($event, 2);
        $events->setTestMode($event, true);

        foreach (array_slice($labels, 0, 6) as $label) {
            $participants->add($event, ['label' => $label]);
        }
    }

    /**
     * Fixed results in match order (group, match, id), cycling through RESULTS; with $limit only the first
     * matches are scored: 1/$divisor of them (a round that is still being played).
     */
    private function score(RoundService $rounds, MixedRound $round, ?int $divisor): void
    {
        $matches = MixedMatch::query()->where('round_id', $round->id)
            ->orderBy('group_no')->orderBy('match_no')->orderBy('id')->get();

        $count = $divisor === null ? $matches->count() : intdiv($matches->count(), $divisor);

        foreach ($matches->take($count) as $n => $match) {
            $rounds->recordScore($round, (int) $match->id, self::RESULTS[$n % count(self::RESULTS)]);
        }
    }
}
