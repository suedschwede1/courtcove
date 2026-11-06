<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Builds the entries of an area sidebar with one rule for all areas.
 *
 * Visibility: every entry has exactly one rule, checked by allows(): null (everyone, guests included), an ability
 * name (through the Gate, so privilege rights end in User::can()) or a closure receiving the viewer (null for a
 * guest) that decides itself, typically with $user->can(...) or a policy ability through the Gate. How rights
 * behave (e.g. an admin mode) therefore lives in User::can() and the policies, never here.
 *
 * Active entry: an entry is active when its matcher (route-name patterns, a closure on the request or a bool)
 * matches and no entry built before it was active, so a sidebar has at most one aria-current="page". Sub-entries
 * are built before their parent and win; the parent is then `current`.
 */
final class AreaNavBuilder
{
    private bool $activeSeen = false;

    public function __construct(
        public readonly ?User $user,
        public readonly Request $request,
    ) {}

    /**
     * The single visibility check of an entry.
     *
     * @param  string|(Closure(?User): bool)|null  $rule
     */
    public function allows(string|Closure|null $rule): bool
    {
        if ($rule === null) {
            return true;
        }

        if ($rule instanceof Closure) {
            return (bool) $rule($this->user);
        }

        return $this->user !== null && Gate::forUser($this->user)->allows($rule);
    }

    /**
     * An entry, or null when the viewer may not see it.
     *
     * @param  list<string>|(Closure(Request): bool)|bool  $active  route-name patterns, a request check or a fixed value
     * @param  string|(Closure(?User): bool)|null  $visible  see allows()
     * @param  list<AreaNavItem|null>  $children  the sub-group (null entries are left out)
     */
    public function item(
        string $key,
        string $label,
        string $url,
        array|Closure|bool $active = [],
        string|Closure|null $visible = null,
        ?string $badge = null,
        ?string $badgeIcon = null,
        array $children = [],
        bool $current = false,
    ): ?AreaNavItem {
        if (! $this->allows($visible)) {
            return null;
        }

        $children = array_values(array_filter($children, static fn (?AreaNavItem $child): bool => $child !== null));
        $isActive = ! $this->activeSeen && $this->matches($active);
        $this->activeSeen = $this->activeSeen || $isActive;

        foreach ($children as $child) {
            $current = $current || $child->active || $child->current;
        }

        return new AreaNavItem($key, $label, $url, $isActive, ! $isActive && $current, $badge, $badgeIcon, $children);
    }

    /**
     * A group, or null when none of its entries is visible.
     *
     * @param  list<AreaNavItem|null>  $items
     */
    public function group(string $key, string $label, array $items): ?AreaNavGroup
    {
        $items = array_values(array_filter($items, static fn (?AreaNavItem $item): bool => $item !== null));

        return $items === [] ? null : new AreaNavGroup($key, $label, $items);
    }

    /**
     * @param  list<AreaNavGroup|null>  $groups
     * @return list<AreaNavGroup>
     */
    public static function groups(array $groups): array
    {
        return array_values(array_filter($groups, static fn (?AreaNavGroup $group): bool => $group !== null));
    }

    /**
     * @param  list<string>|(Closure(Request): bool)|bool  $active
     */
    private function matches(array|Closure|bool $active): bool
    {
        if (is_bool($active)) {
            return $active;
        }

        if ($active instanceof Closure) {
            return (bool) $active($this->request);
        }

        return $active !== [] && $this->request->routeIs(...$active);
    }
}
