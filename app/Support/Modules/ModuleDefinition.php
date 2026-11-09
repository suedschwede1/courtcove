<?php

declare(strict_types=1);

namespace App\Support\Modules;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/**
 * What a module tells the system about itself (registered in a service provider's boot(), see ModuleRegistry).
 * Menu, module overview, administration group, rights list and module sidebar read only this.
 */
final readonly class ModuleDefinition
{
    /**
     * @param  string  $key  stable key (lower case, e.g. "pyramid"); also the key of the module's menu entry
     * @param  string  $name  translation key of the module name (de + en)
     * @param  string  $icon  icon name of the design system (x-ui.icon)
     * @param  string  $description  translation key of the one-line description shown in the overview
     * @param  string  $route  name of the entry route; a module whose route does not exist is not listed anywhere
     * @param  list<string>  $routePatterns  route-name patterns of all pages of the module (current module, active menu entry)
     * @param  class-string<ModuleNavigation>|null  $navigation  sidebar entries; without it the sidebar shows only the module heading
     * @param  string|null  $sidebarGroup  sidebar group key (default: the key); modules with the same group share one group
     * @param  list<ModuleAdminEntry>  $adminEntries  entries in the administration group "Module"
     * @param  array<string, string>  $privileges  rights the module adds: privilege => translation key of its label.
     *                                             Always privileged rights (only count in admin mode).
     * @param  (Closure(?User): bool)|null  $visible  who sees the module in menu and overview (default: every logged-in user)
     * @param  (Closure(): string)|null  $url  entry URL when it is not simply route($route) (e.g. a separate host)
     * @param  bool  $inMenu  false: only in the overview and the sidebar, not in the main menu
     * @param  int  $order  position (ascending) in menu, overview, sidebar and administration group
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $icon,
        public string $description,
        public string $route,
        public array $routePatterns = [],
        public ?string $navigation = null,
        public ?string $sidebarGroup = null,
        public array $adminEntries = [],
        public array $privileges = [],
        public ?Closure $visible = null,
        public ?Closure $url = null,
        public bool $inMenu = true,
        public int $order = 100,
    ) {
        if (preg_match('/^[a-z][a-z0-9_-]*$/', $key) !== 1) {
            throw new InvalidArgumentException("Module key [{$key}] must be lower case letters, digits, - or _.");
        }
    }

    public function label(): string
    {
        return __($this->name);
    }

    public function summary(): string
    {
        return __($this->description);
    }

    public function group(): string
    {
        return $this->sidebarGroup ?? $this->key;
    }

    public function available(): bool
    {
        return Route::has($this->route);
    }

    public function visibleTo(?User $user): bool
    {
        return $this->visible === null ? $user !== null : (bool) ($this->visible)($user);
    }

    public function entryUrl(): string
    {
        return $this->url === null ? route($this->route) : ($this->url)();
    }

    public function sidebar(): ?ModuleNavigation
    {
        return $this->navigation === null ? null : app($this->navigation);
    }

    public function matches(Request $request): bool
    {
        return $this->routePatterns !== [] && $request->routeIs(...$this->routePatterns);
    }
}
