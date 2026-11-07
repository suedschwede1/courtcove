<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

/**
 * Result of one dues generation: how many member and household dues were created and, per reason,
 * which members or households got none (or need a manual look). Flashed to the session as an array.
 */
final class DuesGenerationReport
{
    /** Reasons in display order. */
    public const REASONS = [
        'no_category', 'no_rate', 'deactivated_category',
        'household_too_small', 'household_no_rate', 'household_deactivated',
        'own_due_replaceable', 'own_due_paid',
    ];

    /**
     * @param  array<string, list<array{label: string, url: string}>>  $skipped
     */
    public function __construct(
        public readonly string $yearName,
        public readonly int $createdMembers,
        public readonly int $createdHouseholds,
        public readonly int $createdFromMemberCategory,
        public readonly array $skipped,
    ) {}

    public function total(): int
    {
        return $this->createdMembers + $this->createdHouseholds;
    }

    public function skippedCount(): int
    {
        return array_sum(array_map('count', $this->skipped));
    }

    /** @return array{yearName: string, createdMembers: int, createdHouseholds: int, createdFromMemberCategory: int, skipped: array<string, list<array{label: string, url: string}>>} */
    public function toArray(): array
    {
        return [
            'yearName' => $this->yearName,
            'createdMembers' => $this->createdMembers,
            'createdHouseholds' => $this->createdHouseholds,
            'createdFromMemberCategory' => $this->createdFromMemberCategory,
            'skipped' => $this->skipped,
        ];
    }

    /** Rebuilds a report from toArray() output (the session flash); anything malformed becomes empty. */
    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data) || ! isset($data['yearName'], $data['skipped']) || ! is_array($data['skipped'])) {
            return null;
        }

        $skipped = [];
        foreach (self::REASONS as $reason) {
            $rows = $data['skipped'][$reason] ?? [];
            $skipped[$reason] = array_values(array_filter(
                is_array($rows) ? $rows : [],
                fn (mixed $row): bool => is_array($row) && is_string($row['label'] ?? null) && is_string($row['url'] ?? null),
            ));
        }

        return new self(
            (string) $data['yearName'],
            (int) ($data['createdMembers'] ?? 0),
            (int) ($data['createdHouseholds'] ?? 0),
            (int) ($data['createdFromMemberCategory'] ?? 0),
            $skipped,
        );
    }
}
