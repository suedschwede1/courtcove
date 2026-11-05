<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Services;

use InvalidArgumentException;

/**
 * A score the rules reject. The message is a translation key so the caller renders it in the user's language.
 */
class InvalidScoreException extends InvalidArgumentException
{
    /**
     * @param  string  $key  full translation key, e.g. tournament-base::score.set_range
     * @param  array<string, scalar>  $replace
     */
    public function __construct(public readonly string $key, public readonly array $replace = [])
    {
        parent::__construct($key);
    }
}
