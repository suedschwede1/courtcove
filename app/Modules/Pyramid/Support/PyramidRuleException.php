<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Support;

use RuntimeException;

/**
 * Thrown when a ladder rule forbids an action; carries the translatable verdict.
 */
final class PyramidRuleException extends RuntimeException
{
    public function __construct(public readonly Verdict $verdict)
    {
        parent::__construct($verdict->key);
    }
}
