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
     * Bounds follow the columns (label varchar(120), itn decimal(3,1)), so a strict MySQL never sees an
     * out-of-range value. Array values (`label[]=x`) count as empty.
     *
     * @throws MixedRuleException name_required, name_too_long, itn_range, age_range
     */
    public static function parse(array $post): array
    {
        $scalar = static fn (string $key): string => isset($post[$key]) && is_scalar($post[$key]) ? trim((string) $post[$key]) : '';

        $label = $scalar('label');
        if ($label === '') {
            throw new MixedRuleException('name_required');
        }
        if (mb_strlen($label) > 120) {
            throw new MixedRuleException('name_too_long');
        }

        $gender = $post['gender'] ?? null;
        $gender = in_array($gender, ['m', 'f'], true) ? $gender : null;

        $itn = str_replace(',', '.', $scalar('itn'));
        if ($itn !== '' && (! is_numeric($itn) || (float) $itn < 0 || (float) $itn > 99.9)) {
            throw new MixedRuleException('itn_range');
        }
        $itn = $itn !== '' ? (float) $itn : null;

        $age = $scalar('age');
        if ($age !== '' && (! ctype_digit($age) || (int) $age > 120)) {
            throw new MixedRuleException('age_range');
        }

        return [
            'label' => $label,
            'gender' => $gender,
            'itn' => $itn,
            'age' => $age !== '' ? (int) $age : null,
            'no_extra_match' => $scalar('no_extra_match') !== '' && $scalar('no_extra_match') !== '0',
        ];
    }
}
