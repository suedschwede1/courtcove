<?php

declare(strict_types=1);

namespace App\Modules\Draw\Support;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Support\Modules\ModuleNavigation;
use App\Support\Navigation\AreaNavBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Sidebar entries of the draws: Losprotokolle (sub-group of record pages), Token-Ziehung (an open token draw the
 * viewer is in the pot of) and Verwaltung (right admin.draw through User::can()).
 *
 * A record can be public, but its slug is random and not listed anywhere: logged-in users see the newest records of
 * the current real championship, guests (config require_login off) only the record they are on.
 */
final class DrawNavigation implements ModuleNavigation
{
    private const RECORDS = 5;

    public function entries(AreaNavBuilder $nav): array
    {
        // One record: a plain "Losprotokoll" entry (as in the mockup); several: "Losprotokolle" with the titles below.
        $draws = $this->records($nav->user, $nav->request);
        $records = [];
        foreach ($draws as $draw) {
            $slug = (string) $draw->slug;
            $records[] = $nav->item('record-'.$draw->did,
                $draws->count() === 1 ? __('tournament-base::common.nav.record') : (string) $draw->title,
                route('draw.record', ['slug' => $slug]),
                static fn (Request $r): bool => $r->routeIs('draw.record') && $r->route('slug') === $slug);
        }

        $tokens = $this->tokenDraws($nav->user, $nav->request);
        $tokenItems = [];
        foreach ($tokens as $draw) {
            $did = (int) $draw->did;
            $tokenItems[] = $nav->item('tokens-'.$did,
                $tokens->count() > 1 ? __('tournament-base::common.nav.token_pick_of', ['title' => (string) $draw->title]) : __('tournament-base::common.nav.token_pick'),
                route('draw.tokens', ['did' => $did]),
                static fn (Request $r): bool => $r->routeIs('draw.tokens*') && (int) $r->route('did') === $did,
                static fn (?User $user): bool => $user !== null);
        }

        return [
            ...(count($records) > 1
                ? [$nav->item('records', __('tournament-base::common.nav.records'), $records[0]->url ?? '', false, children: $records)]
                : $records),
            ...$tokenItems,
        ];
    }

    public function settingsEntries(AreaNavBuilder $nav): array
    {
        return [
            $nav->item('admin', __('tournament-base::common.nav.draw_admin'), route('draw.index'), ['draw.index'], 'admin.draw'),
        ];
    }

    /**
     * @return Collection<int, Draw>
     */
    private function records(?User $user, Request $request): Collection
    {
        $draws = new Collection;

        if ($user !== null) {
            // The draws of the current real championship (as TournamentLinks picks it); all draws are in the admin.
            $tid = Tournament::query()->where('is_test', 0)
                ->orderByDesc('active')->orderByDesc('season')->orderByDesc('tid')->value('tid');
            if ($tid !== null) {
                $draws = Draw::query()->where('tid', (int) $tid)->whereNull('discarded')
                    ->orderByDesc('did')->limit(self::RECORDS)->get();
            }
        }

        // The record on screen is always listed, so the page has its entry (guests see only this one).
        $slug = $request->routeIs('draw.record') ? $request->route('slug') : null;
        if (is_string($slug) && ! $draws->contains(static fn (Draw $draw): bool => $draw->slug === $slug)) {
            $current = Draw::query()->where('slug', $slug)->first();
            if ($current !== null) {
                $draws->push($current);
            }
        }

        return $draws;
    }

    /**
     * Open token draws the viewer is in the pot of, plus the token page on screen.
     *
     * @return Collection<int, Draw>
     */
    private function tokenDraws(?User $user, Request $request): Collection
    {
        if ($user === null) {
            return new Collection;
        }

        $tpids = TournamentPlayer::query()->where('uid', (int) $user->uid)->pluck('tpid')->all();
        $dids = $tpids === [] ? [] : DrawEligible::query()->whereIn('tpid', $tpids)->pluck('did')->all();
        $draws = $dids === [] ? new Collection : Draw::query()
            ->whereIn('did', $dids)->where('status', 'picking')->where('mode', 'token')->orderBy('did')->get();

        $did = $request->routeIs('draw.tokens*') ? (int) $request->route('did') : 0;
        if ($did > 0 && ! $draws->contains(static fn (Draw $draw): bool => (int) $draw->did === $did)) {
            $current = Draw::query()->find($did);
            if ($current !== null) {
                $draws->push($current);
            }
        }

        return $draws;
    }
}
