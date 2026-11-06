<?php

declare(strict_types=1);

namespace App\Modules\Draw\Support;

use App\Modules\Draw\Models\Draw;

/**
 * The next admin step of a draw in the overview, derived from the same rules the actions enforce
 * (order: commit, execute, choose, close):
 *
 *  - discarded:                     none
 *  - not executed yet (no seed):    execute (direct and token draws)
 *  - token draw still 'executed':   open (legacy path, see DrawLifecycleService::open)
 *  - picking:                       close
 *  - closed / executed direct:      apply in the tournament admin
 *
 * The old view offered "open" for committed token draws and "execute" for closed ones, both of which
 * the actions then refused; this follows the actions instead.
 */
final class DrawNextStep
{
    public static function for(Draw $draw): string
    {
        return match (true) {
            $draw->discarded !== null && $draw->discarded !== '' => 'none',
            $draw->final_seed === null || $draw->final_seed === '' => 'execute',
            $draw->mode === 'token' && $draw->status === 'executed' => 'open',
            $draw->status === 'picking' => 'close',
            default => 'apply',
        };
    }

    /** Delete is offered for discarded, never executed draws (claimed tokens are checked by the action). */
    public static function canDelete(Draw $draw): bool
    {
        return $draw->discarded !== null && $draw->discarded !== '' && ($draw->final_seed === null || $draw->final_seed === '');
    }

    /** Badge tone of the status (shared status map: committed neutral, picking attention, closed/executed good). */
    public static function tone(Draw $draw): string
    {
        return match ($draw->status) {
            'picking' => 'attention',
            'closed', 'executed' => 'good',
            default => 'neutral',
        };
    }
}
