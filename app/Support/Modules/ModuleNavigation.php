<?php

declare(strict_types=1);

namespace App\Support\Modules;

use App\Support\Navigation\AreaNavBuilder;
use App\Support\Navigation\AreaNavItem;

/**
 * The sidebar part of one module in the module sidebar (ModuleAreaNavigation). On a page of the module the sidebar
 * lists only this module's entries and below them the group "Einstellungen" with its administration entries.
 * Which module is current, its name and its sidebar group come from the ModuleDefinition.
 */
interface ModuleNavigation
{
    /**
     * The module's entries for the builder's viewer; every entry passes exactly one visibility rule.
     *
     * @return list<AreaNavItem|null>
     */
    public function entries(AreaNavBuilder $nav): array;

    /**
     * The module's administration entries for the group "Einstellungen" below the entries; every entry passes
     * exactly one visibility rule, so the group is left out for a viewer without any.
     *
     * @return list<AreaNavItem|null>
     */
    public function settingsEntries(AreaNavBuilder $nav): array;
}
