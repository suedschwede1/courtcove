<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TournamentBase;

use PHPUnit\Framework\Attributes\Test;
use Random\Engine\Mt19937;
use Random\Engine\Secure;
use Random\Randomizer;
use Tests\Concerns\SeedsRandomizer;
use Tests\TestCase;

class TournamentBaseProviderTest extends TestCase
{
    use SeedsRandomizer;

    #[Test]
    public function config_is_merged(): void
    {
        $this->assertNull(config('tournament-base.live_database')); // supplied per environment, never hard-coded
        $this->assertSame([1 => 'Court B', 2 => 'Court D', 3 => 'Court C'], config('tournament-base.courts'));
    }

    #[Test]
    public function randomizer_uses_the_secure_engine(): void
    {
        $this->assertInstanceOf(Secure::class, app(Randomizer::class)->engine);
        $this->assertNotSame(app(Randomizer::class), app(Randomizer::class)); // bind, not singleton
    }

    #[Test]
    public function seeded_randomizer_is_reproducible(): void
    {
        $a = $this->seedRandomizer(42)->getInt(1, 1000000);
        $b = $this->seedRandomizer(42)->getInt(1, 1000000);
        $this->assertSame($a, $b);
        $this->assertInstanceOf(Mt19937::class, app(Randomizer::class)->engine);
    }

    #[Test]
    public function translations_load_in_both_locales(): void
    {
        $this->assertSame('Turnierbereich', __('tournament-base::common.area', [], 'de'));
        $this->assertSame('Tournament area', __('tournament-base::common.area', [], 'en'));
    }
}
