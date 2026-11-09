<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Support\Facades\Route;

/**
 * One entry of a module in the administration sidebar (group "Module"). The entry is listed while its route exists
 * and the viewer passes the ability (a privilege or policy ability through the Gate; null = everyone who sees the
 * administration).
 */
final readonly class ModuleAdminEntry
{
    /**
     * @param  string  $key  stable key, unique inside the administration sidebar
     * @param  string  $route  route name the entry leads to
     * @param  string|null  $ability  Gate ability that shows the entry
     * @param  string  $label  translation key of the entry label
     * @param  list<string>  $patterns  route-name patterns that make the entry the current one
     */
    public function __construct(
        public string $key,
        public string $route,
        public ?string $ability,
        public string $label,
        public array $patterns = [],
    ) {}

    public function available(): bool
    {
        return Route::has($this->route);
    }
}
