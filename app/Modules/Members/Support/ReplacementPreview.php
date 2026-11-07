<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Support\Collection;

/**
 * What "Durch Haushaltsbeitrag ersetzen" would do for one household and fiscal year: which own dues it
 * deletes (unpaid, no payment, no partial amount), which it leaves untouched, the household due it uses
 * (existing) or creates (amount), and why it is not possible, if it is not.
 */
final class ReplacementPreview
{
    /**
     * @param  Collection<int, MemberDue>  $replaceable
     * @param  Collection<int, MemberDue>  $untouched
     */
    public function __construct(
        public readonly Collection $replaceable,
        public readonly Collection $untouched,
        public readonly ?HouseholdDue $existingDue,
        public readonly ?string $amount,
        public readonly ?string $blockReason,
    ) {}

    public function possible(): bool
    {
        return $this->blockReason === null && $this->replaceable->isNotEmpty();
    }
}
