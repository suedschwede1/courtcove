<?php

declare(strict_types=1);

namespace App\Support\Ui;

final readonly class NavItem
{
    public function __construct(
        public string $key,
        public string $label,
        public string $url,
        public string $icon,
        public bool $active,
        public bool $inBar = true,
    ) {}
}
