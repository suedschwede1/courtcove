<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Preflight;

/**
 * A read-only check of one module's tables and data before it is switched over.
 * Implementations are tagged `phase2.preflight` in their service provider.
 */
interface ModulePreflight
{
    /** Name used on the command line, e.g. "draw". */
    public function module(): string;

    /**
     * @return list<PreflightResult>
     */
    public function run(): array;
}
