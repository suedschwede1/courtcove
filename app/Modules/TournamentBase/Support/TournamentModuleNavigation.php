<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Support;

use App\Support\Navigation\AreaNavBuilder;
use App\Support\Navigation\AreaNavItem;
use Illuminate\Http\Request;

/**
 * The sidebar part of one tournament module (Pyramid, championship, draws, Mixed) in the common tournament sidebar
 * (TournamentAreaNavigation). Parts with the same group key share one group (the draws sit in the championship's).
 */
interface TournamentModuleNavigation
{
    /** Stable key (pyramid|championship|draw|mixed). */
    public function key(): string;

    /** Key of the sidebar group the entries go into. */
    public function group(): string;

    /** Module name: group heading (for the group's own module) and phone bar title on its pages. */
    public function label(): string;

    /** False while the module's routes are not registered. */
    public function available(): bool;

    /** Whether the request is a page of this module. */
    public function matches(Request $request): bool;

    /**
     * The module's entries for the builder's viewer; every entry passes exactly one visibility rule.
     *
     * @return list<AreaNavItem|null>
     */
    public function entries(AreaNavBuilder $nav): array;
}
