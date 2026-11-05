<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Support;

use App\Modules\MixedTournament\Domain\MixedRuleException;

/**
 * Validates and normalises the participant form (add and edit share the rules).
 */
final class ParticipantInput
{
    /**
     * @param  array<string, mixed>  $post
     * @return array{label: string, gender: ?string, itn: ?float, age: ?int, no_extra_match: bool}
     *
     * @throws MixedRuleException name_required, age_range
     */
    public static function parse(array $post): array
    {
        $label = trim((string) ($post['label'] ?? ''));
        if ($label === '') {
            throw new MixedRuleException('name_required');
        }

        $gender = $post['gender'] ?? null;
        $gender = in_array($gender, ['m', 'f'], true) ? $gender : null;

        $itn = trim((string) ($post['itn'] ?? ''));
        $itn = $itn !== '' ? (float) $itn : null;

        $age = trim((string) ($post['age'] ?? ''));
        if ($age !== '' && (! ctype_digit($age) || (int) $age > 120)) {
            throw new MixedRuleException('age_range');
        }

        return [
            'label' => $label,
            'gender' => $gender,
            'itn' => $itn,
            'age' => $age !== '' ? (int) $age : null,
            'no_extra_match' => (bool) ($post['no_extra_match'] ?? false),
        ];
    }
}
