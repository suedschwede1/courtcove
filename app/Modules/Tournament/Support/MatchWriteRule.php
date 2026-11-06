<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Support;

use App\Modules\Tournament\Models\Tournament;

/**
 * Who may write a match (date, result, confirmation, deletion): the rule of ZF2's MatchController::load(),
 * in ONE place for the match page, its actions and the match cards (MatchPresenter).
 *
 *   - admins: every match (a confirmed result too)
 *   - participants: only their own matches
 *   - members: nothing, read only
 *   - test mode in a TEST tournament: yes, from the simulated player's view and only for his own matches
 *   - test mode in the real tournament: nothing (entered_uid would claim the admin entered for a player)
 */
final class MatchWriteRule
{
    /** @param  array<string, mixed>  $match  match row (getAttributes) */
    public static function isOwn(array $match, AccessContext $access): bool
    {
        return $access->tpid !== null
            && ((int) $match['tpid_a'] === $access->tpid || (int) $match['tpid_b'] === $access->tpid);
    }

    /** @param  array<string, mixed>  $match  match row (getAttributes) */
    public static function canWrite(array $match, AccessContext $access, Tournament $t): bool
    {
        $isTest = ! empty($t->is_test);

        return ($access->isAdmin() || self::isOwn($match, $access))
            && ($access->canWrite || ($isTest && $access->simulation !== null));
    }

    /**
     * Translation key of the refusal (ZF2 writeDeniedMessage).
     *
     * @param  array<string, mixed>  $match
     */
    public static function deniedKey(array $match, AccessContext $access): string
    {
        if ($access->simulation !== null) {
            return 'tournament::match.denied.simulation';
        }

        if (! self::isOwn($match, $access)) {
            return 'tournament::match.denied.not_own';
        }

        return 'tournament::match.denied.other';
    }
}
