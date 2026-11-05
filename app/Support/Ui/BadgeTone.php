<?php

namespace App\Support\Ui;

use InvalidArgumentException;

enum BadgeTone: string
{
    case Good = 'good';
    case Neutral = 'neutral';
    case Attention = 'attention';
    case Problem = 'problem';
    case You = 'you';

    /** Single source of truth for status key => tone. */
    public const STATUS_MAP = [
        'active' => 'good',
        'played' => 'good',
        'confirmed' => 'good',
        'done' => 'good',
        'running' => 'good',
        'scheduled' => 'neutral',
        'date_agreed' => 'neutral',
        'paused' => 'neutral',
        'subscription' => 'neutral',
        'no_date' => 'attention',
        'open' => 'attention',
        'still_open' => 'attention',
        'pending_approval' => 'attention',
        'cancelled' => 'problem',
        'blocked' => 'problem',
        'rejected' => 'problem',
        'lost' => 'problem',
        'you' => 'you',
    ];

    public static function forStatus(string $status): self
    {
        $tone = self::STATUS_MAP[$status] ?? null;

        if ($tone === null) {
            throw new InvalidArgumentException("No badge tone for status [{$status}]; pass tone explicitly.");
        }

        return self::from($tone);
    }
}
