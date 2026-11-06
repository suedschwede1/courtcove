<?php

declare(strict_types=1);

namespace App\Support\Navigation;

/**
 * A labelled group of an area sidebar.
 */
final readonly class AreaNavGroup
{
    /**
     * @param  list<AreaNavItem>  $items
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $items,
    ) {}
}
