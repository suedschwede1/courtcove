<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Support;

use App\Models\User;
use App\Modules\Pyramid\Support\PyramidNavigation;
use App\Support\Navigation\AreaNavBuilder;
use App\Support\Navigation\AreaNavGroup;
use App\Support\Navigation\AreaNavigation;
use App\Support\Navigation\AreaNavItem;
use Illuminate\Http\Request;

/**
 * Navigation of the tournament area: ONE common sidebar for every page of Pyramid, championship, draws and Mixed
 * (mockups Turnier-Pyramide, Turnier-Menue-Mobil). One group per module with the module name as heading
 * (Forderungspyramide, Vereinsmeisterschaft with the draws, Mixed-/Fun-Turnier) and the module's entries; a group
 * without a visible entry is left out. Exactly one entry of the whole sidebar is the current page.
 */
final class TournamentAreaNavigation implements AreaNavigation
{
    /** Order of the groups and of the parts inside a group. */
    private const MODULES = [
        PyramidNavigation::class,
    ];

    /** @var list<TournamentModuleNavigation>|null */
    private ?array $modules = null;

    public function eyebrow(): string
    {
        return __('tournament-base::common.nav.area');
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
        foreach ($this->modules() as $module) {
            $key = $module->group();
            $groups[$key] ??= ['label' => $module->label(), 'items' => []];
            $groups[$key]['items'] = [...$groups[$key]['items'], ...$module->entries($nav)];
        }

        $result = [];
        foreach ($groups as $key => $group) {
            $result[] = $nav->group($key, $group['label'], $group['items']);
        }

        return AreaNavBuilder::groups($result);
    }

    /** Name of the current module (phone bar title), else the area name. */
    public function moduleLabel(Request $request): string
    {
        foreach ($this->modules() as $module) {
            if ($module->matches($request)) {
                return $module->label();
            }
        }

        return $this->eyebrow();
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
