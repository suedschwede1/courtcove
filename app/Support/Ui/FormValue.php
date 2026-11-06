<?php

declare(strict_types=1);

namespace App\Support\Ui;

/**
 * Scalar-safe values for re-shown form fields: old() returns an array when a rejected request sent `name[]=x`,
 * which would break escaping ("htmlspecialchars(): array given") or string casts. Such values show as empty.
 */
final class FormValue
{
    /** A single field value as a string; arrays and objects become ''. */
    public static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * A list field (checkbox group, multi-select) as a list of strings; a plain value or garbage becomes [].
     *
     * @return list<string>
     */
    public static function list(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(fn ($item) => (string) $item, array_filter($value, 'is_scalar')));
    }
}
