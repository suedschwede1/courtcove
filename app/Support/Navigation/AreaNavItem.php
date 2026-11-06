<?php

declare(strict_types=1);

namespace App\Support\Navigation;

/**
 * One entry of an area sidebar (admin, members, tournaments).
 *
 * `active` marks the current page (aria-current="page", at most one per sidebar); `current` marks an entry that
 * holds the current page without being it: a module of the tournament switcher or the parent of an active
 * sub-entry (aria-current="true"). `badge` is a short trailing text; with `badgeIcon` the icon is shown and the
 * text is read by screen readers only. `children` is a sub-group shown indented below the entry.
 */
final readonly class AreaNavItem
{
    /**
     * @param  list<AreaNavItem>  $children
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $url,
        public bool $active = false,
        public bool $current = false,
        public ?string $badge = null,
        public ?string $badgeIcon = null,
        public array $children = [],
    ) {}
}
