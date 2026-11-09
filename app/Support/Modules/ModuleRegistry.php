<?php

declare(strict_types=1);

namespace App\Support\Modules;

use App\Models\User;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * The one place that knows which modules exist (singleton, bound in AppServiceProvider). A module registers itself
 * in the boot() of its own service provider:
 *
 *     $this->app->make(ModuleRegistry::class)->register(new ModuleDefinition(key: ..., name: ..., ...));
 *
 * The main menu, the module overview (/modules), the administration group "Module", the rights list of the user
 * form and the module sidebar read only from here; adding a module needs no change to any of them.
 */
final class ModuleRegistry
{
    /** @var array<string, array{0: ModuleDefinition, 1: int}> definition and registration sequence by key */
    private array $modules = [];

    private int $sequence = 0;

    /** @var list<ModuleDefinition>|null sorted list, rebuilt after a registration */
    private ?array $sorted = null;

    /**
     * Registers (or replaces) a module. A privilege that the core or another module already defines is refused,
     * so a module can neither relabel nor take over an existing right.
     *
     * @throws InvalidArgumentException
     */
    public function register(ModuleDefinition $module): void
    {
        foreach (array_keys($module->privileges) as $privilege) {
            if (in_array($privilege, User::CORE_PRIVILEGES, true)) {
                throw new InvalidArgumentException("Privilege [{$privilege}] of module [{$module->key}] is a core privilege.");
            }
            foreach ($this->modules as $key => [$other]) {
                if ($key !== $module->key && array_key_exists($privilege, $other->privileges)) {
                    throw new InvalidArgumentException("Privilege [{$privilege}] of module [{$module->key}] is already defined by module [{$key}].");
                }
            }
        }

        $this->modules[$module->key] = [$module, $this->sequence++];
        $this->sorted = null;
    }

    /**
     * All registered modules, by order then registration.
     *
     * @return list<ModuleDefinition>
     */
    public function all(): array
    {
        if ($this->sorted === null) {
            $entries = array_values($this->modules);
            usort($entries, static fn (array $a, array $b): int => [$a[0]->order, $a[1]] <=> [$b[0]->order, $b[1]]);
            $this->sorted = array_map(static fn (array $entry): ModuleDefinition => $entry[0], $entries);
        }

        return $this->sorted;
    }

    /**
     * The modules whose entry route exists (the others are not listed anywhere).
     *
     * @return list<ModuleDefinition>
     */
    public function available(): array
    {
        return array_values(array_filter($this->all(), static fn (ModuleDefinition $m): bool => $m->available()));
    }

    public function find(string $key): ?ModuleDefinition
    {
        return $this->modules[$key][0] ?? null;
    }

    /** The available module the request is a page of (null: none). */
    public function current(Request $request): ?ModuleDefinition
    {
        foreach ($this->available() as $module) {
            if ($module->matches($request)) {
                return $module;
            }
        }

        return null;
    }

    /** Whether the request is a page of any module. */
    public function inArea(Request $request): bool
    {
        return $this->current($request) !== null;
    }

    /**
     * Privileges added by modules: privilege => translation key of the label.
     *
     * @return array<string, string>
     */
    public function privileges(): array
    {
        $privileges = [];
        foreach ($this->all() as $module) {
            $privileges = [...$privileges, ...$module->privileges];
        }

        return $privileges;
    }

    /**
     * Administration entries of the available modules, in module order (entries with a missing route left out).
     *
     * @return list<ModuleAdminEntry>
     */
    public function adminEntries(): array
    {
        $entries = [];
        foreach ($this->available() as $module) {
            foreach ($module->adminEntries as $entry) {
                if ($entry->available()) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }
}
