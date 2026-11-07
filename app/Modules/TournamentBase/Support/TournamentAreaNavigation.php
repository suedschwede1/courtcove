<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Support;

use App\Models\User;
use App\Modules\Draw\Support\DrawNavigation;
use App\Modules\MixedTournament\Support\MixedNavigation;
use App\Modules\Pyramid\Support\PyramidNavigation;
use App\Modules\Tournament\Support\ChampionshipNavigation;
use App\Support\Navigation\AreaNavBuilder;
use App\Support\Navigation\AreaNavGroup;
use App\Support\Navigation\AreaNavigation;
use App\Support\Navigation\AreaNavItem;
use Illuminate\Http\Request;

/**
 * Navigation of the tournament area. Every tournament is standalone with its own sidebar: on a page of Pyramid,
 * the championship (with its draws) or Mixed the sidebar lists only that module's entries (group heading = module
 * name, eyebrow = module name) and below it the group "Einstellungen" with the module's administration entries. Only a page that belongs to no module (e.g. an error page inside the area) gets the
 * groups of all modules. A group without a visible entry is left out; exactly one entry is the current page.
 */
final class TournamentAreaNavigation implements AreaNavigation
{
    /** Order of the groups and of the parts inside a group. */
    private const MODULES = [
        PyramidNavigation::class,
        ChampionshipNavigation::class,
        DrawNavigation::class,
        MixedNavigation::class,
    ];

    /** @var list<TournamentModuleNavigation>|null */
    private ?array $modules = null;

    public function eyebrow(): string
    {
        return $this->moduleLabel(request());
    }

    public function label(): string
    {
        return __('tournament-base::common.area');
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
        $current = $this->current($request);
        foreach ($this->modules() as $module) {
            if ($current !== null && $module->group() !== $current->group()) {
                continue;
            }

            $key = $module->group();
            $groups[$key] ??= ['label' => $module->label(), 'items' => []];
            $groups[$key]['items'] = [...$groups[$key]['items'], ...$module->entries($nav)];
            $settings[$key] = [...($settings[$key] ?? []), ...$module->settingsEntries($nav)];
        }

        $result = [];
        foreach ($groups as $key => $group) {
            $result[] = $nav->group($key, $group['label'], $group['items']);
            $result[] = $nav->group($key.'-settings', __('tournament-base::common.nav.settings'), $settings[$key] ?? []);
        }

        return AreaNavBuilder::groups($result);
    }

    /** Name of the current module (phone bar title), else the area name. */
    public function moduleLabel(Request $request): string
    {
        $current = $this->current($request);

        return $current !== null ? $current->label() : __('tournament-base::common.nav.area');
    }

    /** The module the request is a page of (null: none). */
    private function current(Request $request): ?TournamentModuleNavigation
    {
        foreach ($this->modules() as $module) {
            if ($module->matches($request)) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @return list<TournamentModuleNavigation>
     */
    private function modules(): array
    {
        if ($this->modules === null) {
            $this->modules = [];
            foreach (self::MODULES as $class) {
                $module = app($class);
                if ($module->available()) {
                    $this->modules[] = $module;
                }
            }
        }

        return $this->modules;
    }
}
