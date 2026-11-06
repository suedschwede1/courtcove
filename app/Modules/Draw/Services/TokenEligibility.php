<?php

declare(strict_types=1);

namespace App\Modules\Draw\Services;

use App\Modules\Draw\Models\DrawToken;
use App\Modules\Tournament\Services\TournamentManager;

/**
 * Partner rule of the token pick (ported from ZF2 TokenController::blockedTokens).
 *
 * Tokens leading into the group of the player's partner are refused. Once the partner's group is known,
 * every still free token with a slot in that group is blocked. If the partner has not picked (and is not
 * a seeded head), there is nothing to block yet: whoever picks first picks freely.
 *
 * Blocked tokens are not shown at all, without any reason: a hint like "leads into your partner's group"
 * would be a statement about the hidden assignment.
 */
final class TokenEligibility
{
    private const SLOT_GROUP = '/^([A-Z]+)[0-9]+$/';

    /**
     * @param  array<string, mixed>|null  $player  the picking player's row
     * @return array<int, true> dtid => true
     */
    public function blockedTokens(int $did, ?array $player, TournamentManager $m, int $tid): array
    {
        if (! $player || empty($player['partner_tpid'])) {
            return [];
        }

        $partnerTpid = (int) $player['partner_tpid'];
        $partnerGroups = [];

        // The partner's group has TWO sources: his player row (seeded head or already allocated; seeded
        // players never hold a token) and the slot of the token he claimed himself.
        $partner = $m->player($tid, $partnerTpid);

        if ($partner && ! empty($partner['grp'])) {
            $partnerGroups[] = (string) $partner['grp'];
        }

        $rows = DrawToken::query()->where('did', $did)->orderBy('dtid')->get();

        foreach ($rows as $row) {
            if ((int) $row->claimed_tpid !== $partnerTpid || ! $row->slot) {
                continue;
            }

            // The slot is group plus position, e.g. F3.
            if (preg_match(self::SLOT_GROUP, (string) $row->slot, $match)
                && ! in_array($match[1], $partnerGroups, true)) {
                $partnerGroups[] = $match[1];
            }
        }

        if (! $partnerGroups) {
            return [];
        }

        $blocked = [];

        foreach ($rows as $row) {
            if ($row->claimed_tpid || ! $row->slot) {
                continue;
            }

            if (! preg_match(self::SLOT_GROUP, (string) $row->slot, $match)) {
                continue;
            }

            if (in_array($match[1], $partnerGroups, true)) {
                $blocked[(int) $row->dtid] = true;
            }
        }

        // If nothing else is left, the block is lifted: the player would face an empty list, and the
        // fallback on close would put him into the partner's group anyway. Then he may as well pick himself.
        $freeUnblocked = 0;

        foreach ($rows as $row) {
            if (! $row->claimed_tpid && ! isset($blocked[(int) $row->dtid])) {
                $freeUnblocked++;
            }
        }

        if (! $freeUnblocked) {
            return [];
        }

        return $blocked;
    }
}
