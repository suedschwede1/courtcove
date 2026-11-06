<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Support;

use RuntimeException;

/**
 * A rule refused a module action. Services throw it with a translation key; controllers flash
 * `__($key, $replace)` as an error (ZF2 flashed the exception text).
 */
final class ActionRefused extends RuntimeException
{
    /** @param  array<string, mixed>  $replace */
    public function __construct(public readonly string $key, public readonly array $replace = [])
    {
        parent::__construct($key);
    }
}
