<?php

declare(strict_types=1);

namespace App\Modules\Draw\Services;

use RuntimeException;

/**
 * The announced beacon round is not in the future. The message keeps the ZF2 text;
 * the UI shows the translated key draw::admin.round_not_future with :round and :current.
 */
final class RoundNotFutureException extends RuntimeException
{
    public function __construct(public readonly int $round, public readonly int $current)
    {
        parent::__construct(sprintf('drand-Runde %u ist nicht zukünftig (aktuelle Runde: %u).', $round, $current));
    }
}
