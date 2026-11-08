<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Staging;

/**
 * Cleans configuration and personal data outside the module tables when `phase2:scrub-staging` runs.
 * Implementations are tagged `phase2.config-scrub` in a service provider.
 */
interface ConfigScrub
{
    /** Name used in the command output, e.g. "whatsapp". */
    public function name(): string;

    /**
     * Performs the scrub and returns the names of what was changed (option keys, table names).
     * Never returns values.
     *
     * @return list<string>
     */
    public function scrub(): array;
}
