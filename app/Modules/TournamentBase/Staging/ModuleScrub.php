<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Staging;

use Illuminate\Database\Seeder;

/**
 * Describes the module-owned tables that `phase2:scrub-staging` empties and the
 * seeder that refills them with fictional demo data.
 * Implementations are tagged `phase2.scrub` in their service provider.
 */
interface ModuleScrub
{
    /** Name used in the command output, e.g. "draw". */
    public function module(): string;

    /**
     * Only the module's own tables, nothing else.
     *
     * @return list<string>
     */
    public function tables(): array;

    /**
     * @return class-string<Seeder>
     */
    public function seeder(): string;
}
