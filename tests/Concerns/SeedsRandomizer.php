<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Random\Engine\Mt19937;
use Random\Randomizer;

trait SeedsRandomizer
{
    protected function seedRandomizer(int $seed = 42): Randomizer
    {
        $randomizer = new Randomizer(new Mt19937($seed));
        $this->app->instance(Randomizer::class, $randomizer);

        return $randomizer;
    }
}
