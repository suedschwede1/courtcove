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
     * A result over 120 years is a placeholder birth date in the member export (e.g. 01.01.1900), not an
     * age: it counts as unknown, so a stored age is always 0..120 or null (normalizeAge() would refuse it).
     *
     * @return int|null null for an empty or invalid date, a birth date after $atDate or more than 120 years
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

        $years = $birth->diff($at)->y;

        return $years <= 120 ? $years : null;
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
