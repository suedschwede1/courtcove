<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Domain;

use RuntimeException;

/**
 * A violated tournament rule or input rule. The message IS the translation key
 * (`mixed::messages.<key>`); controllers flash
 * `__('mixed::messages.'.$e->key, $e->replace)`.
 */
final class MixedRuleException extends RuntimeException
{
    /** @param  array<string, string|int|float>  $replace */
    public function __construct(public readonly string $key, public readonly array $replace = [])
    {
        parent::__construct($key);
    }
}
