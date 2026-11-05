<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Services;

use DateTime;
use Exception;
use InvalidArgumentException;

/**
 * Age handling of the participants (old MixedManager::ageFromBirthDate() and
 * normalizeAge()).
 */
final class AgeCalculator
{
    /**
     * Age in full years at the given date (event date, not "today") - the basis
     * of the participants' age field, which is calculated once when taking
     * over from the member file (it only knows the birth date). The age mode
     * of the grouping afterwards only reads the stored age.
     *
     * @return int|null null for an empty or invalid date or a birth date after $atDate
     */
    public function ageFromBirthDate(?string $birthDate, string $atDate): ?int
    {
        if (! $birthDate) {
            return null;
        }
        try {
            $birth = new DateTime($birthDate);
            $at = new DateTime($atDate);
        } catch (Exception) {
            return null;
        }

        if ($birth > $at) {
            return null;
        }

        return $birth->diff($at)->y;
    }

    /**
     * @return int|null null for null or ''
     *
     * @throws InvalidArgumentException when not an integer between 0 and 120
     */
    public function normalizeAge(mixed $age): ?int
    {
        if ($age === null || $age === '') {
            return null;
        }
        if (filter_var($age, FILTER_VALIDATE_INT) === false || (int) $age < 0 || (int) $age > 120) {
            throw new InvalidArgumentException('age must be between 0 and 120');
        }

        return (int) $age;
    }
}
