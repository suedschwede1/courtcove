<?php

declare(strict_types=1);

namespace App\Support\Ui;

final readonly class AdminNavGroup
{
    /**
     * @param  list<NavItem>  $items
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $items,
    ) {}
}
