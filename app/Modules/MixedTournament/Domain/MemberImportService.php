<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Domain;

use App\Modules\MixedTournament\Models\MixedMember;
use App\Modules\MixedTournament\Services\MemberCsvParser;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the club-wide member roster by a fresh member export. The export
 * is a full dump, not a delta, so it is delete + insert in ONE transaction:
 * a failing row keeps the old roster.
 */
final class MemberImportService
{
    public function __construct(private readonly MemberCsvParser $parser) {}

    /**
     * @return int number of parsed rows (as the old code reported it)
     *
     * @throws MixedRuleException csv_column_missing, csv_label_too_long
     */
    public function import(string $content): int
    {
        $parsed = $this->parser->parse($content);

        if ($parsed['missing_column'] !== null) {
            throw new MixedRuleException('csv_column_missing', ['column' => $parsed['missing_column']]);
        }

        // bs_mixed_members.label is varchar(120): refuse the whole file instead of a strict-mode 500.
        foreach ($parsed['rows'] as $row) {
            if (mb_strlen($row['label']) > 120) {
                throw new MixedRuleException('csv_label_too_long', ['label' => mb_substr($row['label'], 0, 40)]);
            }
        }

        DB::transaction(function () use ($parsed): void {
            MixedMember::query()->delete();

            foreach ($parsed['rows'] as $row) {
                if ($row['label'] === '') {
                    continue;
                }

                MixedMember::query()->create([
                    'label' => $row['label'],
                    'gender' => $row['gender'],
                    'itn' => $row['itn'],
                    'birth_date' => $row['birth_date'],
                ]);
            }
        });

        return count($parsed['rows']);
    }

    public function count(): int
    {
        return MixedMember::query()->count();
    }
}
