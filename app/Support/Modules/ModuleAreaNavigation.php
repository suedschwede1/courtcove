<?php

declare(strict_types=1);

namespace App\Support\Modules;

use App\Models\User;
use App\Support\Navigation\AreaNavBuilder;
use App\Support\Navigation\AreaNavGroup;
use App\Support\Navigation\AreaNavigation;
use App\Support\Navigation\AreaNavItem;
use Illuminate\Http\Request;

/**
 * Navigation of the module area, read from the ModuleRegistry. Every module has its own sidebar: on a page of a
 * module the sidebar lists only that module's entries (group heading = module name, eyebrow = module name) and below
 * them the group "Einstellungen" with the module's administration entries. Only a page that belongs to no module
 * (the module overview, an error page inside the area) gets the groups of all modules. A group without a visible
 * entry is left out; exactly one entry is the current page.
 */
final class ModuleAreaNavigation implements AreaNavigation
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    public function eyebrow(): string
    {
        return $this->moduleLabel(request());
    }

    public function label(): string
    {
        return __('modules.area');
    }

    /**
     * @return list<AreaNavGroup>
     */
    public function groups(?User $user, Request $request): array
    {
        $nav = new AreaNavBuilder($user, $request);

        /** @var array<string, array{label: string, items: list<AreaNavItem|null>}> $groups */
        $groups = [];
        /** @var array<string, list<AreaNavItem|null>> $settings */
        $settings = [];
        $current = $this->registry->current($request);
        foreach ($this->registry->available() as $module) {
            if ($current !== null && $module->group() !== $current->group()) {
                continue;
            }

            $sidebar = $module->sidebar();
            if ($sidebar === null) {
                continue;
            }

            $key = $module->group();
            $groups[$key] ??= ['label' => $module->label(), 'items' => []];
            $groups[$key]['items'] = [...$groups[$key]['items'], ...$sidebar->entries($nav)];
            $settings[$key] = [...($settings[$key] ?? []), ...$sidebar->settingsEntries($nav)];
        }

        $result = [];
        foreach ($groups as $key => $group) {
            $result[] = $nav->group($key, $group['label'], $group['items']);
            $result[] = $nav->group($key.'-settings', __('modules.settings'), $settings[$key] ?? []);
        }

        return AreaNavBuilder::groups($result);
    }

    /** Name of the current module (phone bar title), else the area name. */
    public function moduleLabel(Request $request): string
    {
        return $this->registry->current($request)?->label() ?? __('modules.area');
    }
}
