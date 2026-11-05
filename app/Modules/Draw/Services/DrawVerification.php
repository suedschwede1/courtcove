<?php

declare(strict_types=1);

namespace App\Modules\Draw\Services;

/**
 * Outcome of recomputing one stored draw. A check is true (passed), false
 * (failed) or null (not applicable, e.g. no reveal yet).
 */
final readonly class DrawVerification
{
    /**
     * @param  array<string, bool|null>  $checks  canonical|commitment|final_seed|result|tokens
     */
    public function __construct(
        public string $slug,
        public string $status,
        public string $mode,
        public array $checks,
    ) {}

    public function ok(): bool
    {
        return ! in_array(false, $this->checks, true);
    }

    /**
     * @return list<string>
     */
    public function failures(): array
    {
        return array_keys(array_filter($this->checks, fn ($check): bool => $check === false));
    }

    public function executed(): bool
    {
        return ($this->checks['final_seed'] ?? null) !== null;
    }
}
