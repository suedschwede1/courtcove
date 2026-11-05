<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Support;

/**
 * Display text and badge tone of challenge and player statuses.
 */
final class PyramidStatus
{
    private const GROUPS = [
        'open' => 'attention',
        'scheduled' => 'neutral',
        'played' => 'good',
        'declined' => 'problem',
        'withdrawn' => 'neutral',
        'forfeited' => 'problem',
        'expired' => 'neutral',
        'active' => 'good',
        'paused' => 'neutral',
        'waiting' => 'attention',
        'entering' => 'attention',
        'removed' => 'problem',
    ];

    /**
     * Label of a challenge status; an unknown status is shown as stored.
     */
    public static function label(string $challengeStatus): string
    {
        $key = 'pyramid::ui.status.'.$challengeStatus;
        $text = __($key);

        return $text === $key ? $challengeStatus : $text;
    }

    /**
     * Badge tone (good, neutral, attention, problem) of a challenge or player status.
     */
    public static function badgeGroup(string $status): string
    {
        return self::GROUPS[$status] ?? 'neutral';
    }
}
