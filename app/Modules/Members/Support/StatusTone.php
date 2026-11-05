<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

/**
 * Single source of truth for the badge tone of member and due statuses
 * (values are App\Support\Ui\BadgeTone cases).
 */
final class StatusTone
{
    public const MEMBER = [
        'aktiv' => 'good',
        'ruhend' => 'attention',
        'ausgetreten' => 'neutral',
    ];

    public const DUE = [
        'bezahlt' => 'good',
        'ausstehend' => 'attention',
        'ueberfaellig' => 'problem',
    ];

    public static function member(?string $status): string
    {
        return self::MEMBER[$status ?? ''] ?? 'neutral';
    }

    public static function due(?string $status): string
    {
        return self::DUE[$status ?? ''] ?? 'neutral';
    }

    /**
     * Maps each status of a tone map to its badge modifier class (for Alpine class bindings).
     *
     * @param  array<string, string>  $map
     * @return array<string, string>
     */
    public static function badgeClasses(array $map): array
    {
        return array_map(fn (string $tone): string => 'ds-badge--'.$tone, $map);
    }
}
