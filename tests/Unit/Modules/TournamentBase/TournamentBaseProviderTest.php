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
        $this->assertSame([], config('tournament-base.courts')); // names are supplied per environment
    }

    #[Test]
    public function configured_courts_are_read_from_config(): void
    {
        config()->set('tournament-base.courts', [1 => 'Alpha', 2 => 'Beta', 3 => 'Gamma']);

        $this->assertSame('Beta', config('tournament-base.courts')[2]);
    }

    #[Test]
    public function env_style_json_is_decoded_and_invalid_json_falls_back_to_empty(): void
    {
        $load = function (string $json): array {
            putenv('TOURNAMENT_COURTS='.$json);
            try {
                return (require base_path('config/tournament-base.php'))['courts'];
            } finally {
                putenv('TOURNAMENT_COURTS');
            }
        };

        $this->assertSame([1 => 'Alpha', 2 => 'Beta'], $load('{"1":"Alpha","2":"Beta"}'));
        $this->assertSame([], $load('not json'));
        $this->assertSame([], $load('"scalar"'));
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
        $this->assertSame('Module', __('tournament-base::common.area', [], 'de'));
        $this->assertSame('Modules', __('tournament-base::common.area', [], 'en'));
    }
}
