<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

/** One "possible family": member ids (ascending), the reasons (address, email, phone, lastname) and its signature. */
final class HouseholdSuggestion
{
    /**
     * @param  list<int>  $memberIds
     * @param  list<string>  $reasons
     */
    public function __construct(
        public readonly array $memberIds,
        public readonly array $reasons,
        public readonly string $signature,
    ) {}
}
