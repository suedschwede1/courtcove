<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The navigation of one area of the area shell (layouts.admin with x-layout.admin-sidebar): administration,
 * members, tournaments. A provider lists its groups for the viewer (null: a guest) and the current request;
 * entries are built through AreaNavBuilder, so visibility, the active entry and sub-groups follow one rule.
 */
interface AreaNavigation
{
    /** Small label under the club name in the sidebar head. */
    public function eyebrow(): string;

    /** Accessible name of the sidebar and its navigation. */
    public function label(): string;

    /**
     * @return list<AreaNavGroup>
     */
    public function groups(?User $user, Request $request): array;
}
