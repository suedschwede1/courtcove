<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Support;

use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedRound;

/**
 * Module tabs of an event (old partial/nav.phtml order): Teilnehmer, the rounds, + Neue Runde,
 * Durchmischung, Wertung, Konfiguration.
 *
 * Americano: one tab per round by id, a completed round carries `done` (rendered as a check icon).
 * Mexicano plays 10-15 short rounds, so the old nav-mexicano.phtml showed one round picker instead of
 * a tab per round; here it is one "Runden" tab (to the open round, else the last one) and the round
 * page shows the round chips.
 *
 * `$active`: teilnehmer | round:<id> | neue-runde | durchmischung | wertung | konfiguration.
 * `$adminView` picks the mixed.admin.* routes, `$canAdmin` adds the admin-only tabs; a scorer in the
 * admin view (adminView without canAdmin) sees no Teilnehmer tab.
 */
final class MixedTabs
{
    /** @return list<array{label: string, href: string, active: bool, done: bool}> */
    public static function items(MixedEvent $e, string $active, bool $adminView, bool $canAdmin): array
    {
        $prefix = $adminView ? 'mixed.admin.' : 'mixed.';
        $event = ['event' => (int) $e->id];
        $rounds = MixedRound::query()->where('event_id', $e->id)->orderBy('id')->get();
        $items = [];

        if ($canAdmin || ! $adminView) {
            $items[] = self::item(__('mixed::common.tab_participants'), route($prefix.'event', $event), $active === 'teilnehmer');
        }

        if ($e->isMexicano()) {
            if ($rounds->isNotEmpty()) {
                $target = $rounds->first(fn (MixedRound $r): bool => ! $r->isCompleted()) ?? $rounds->last();
                $items[] = self::item(
                    __('mixed::common.tab_rounds'),
                    route($prefix.'round', ['round' => (int) $target->id]),
                    str_starts_with($active, 'round:'),
                );
            }
        } else {
            foreach ($rounds as $round) {
                $items[] = self::item(
                    __('mixed::common.tab_round', ['number' => (int) $round->round_no]),
                    route($prefix.'round', ['round' => (int) $round->id]),
                    $active === 'round:'.$round->id,
                    $round->isCompleted(),
                );
            }
        }

        if ($canAdmin) {
            $items[] = self::item(__('mixed::common.tab_new_round'), route('mixed.admin.new-round', $event), $active === 'neue-runde');
        }

        $items[] = self::item(__('mixed::common.tab_mixing'), route($prefix.'durchmischung', $event), $active === 'durchmischung');
        $items[] = self::item(__('mixed::common.tab_standings'), route($prefix.'standings', $event), $active === 'wertung');

        if ($canAdmin) {
            $items[] = self::item(__('mixed::common.tab_config'), route('mixed.admin.config', $event), $active === 'konfiguration');
        }

        return $items;
    }

    /** @return array{label: string, href: string, active: bool, done: bool} */
    private static function item(string $label, string $href, bool $active, bool $done = false): array
    {
        return ['label' => $label, 'href' => $href, 'active' => $active, 'done' => $done];
    }
}
