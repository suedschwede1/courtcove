<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use Illuminate\Support\Carbon;

/** What the admin pages show about the channel: budget threshold notice and pause. No secrets, no numbers. */
final class WhatsAppAdminStatus
{
    private const TIMEZONE = 'Europe/Vienna';

    public function __construct(
        private readonly WhatsAppSettings $settings,
        private readonly BudgetService $budget,
    ) {}

    /**
     * @return array{state: 'warn'|'stopped'|null, percent: int, resumeDate: string, paused: bool}
     */
    public function notices(): array
    {
        $snapshot = $this->budget->current();
        $threshold = $snapshot->budgetMicros > 0 ? $this->budget->thresholdState() : 'ok';
        $state = $threshold === 'ok' ? null : $threshold;

        return [
            'state' => $state,
            'percent' => $snapshot->percent,
            'resumeDate' => Carbon::now(self::TIMEZONE)->startOfMonth()->addMonth()->format('d.m.Y'),
            'paused' => $this->settings->pausedReason() !== null,
        ];
    }

    /** Euro amount from millionths, in the notation of the current language. */
    public static function money(int $micros): string
    {
        $german = app()->getLocale() === 'de';

        return number_format($micros / 1_000_000, 2, $german ? ',' : '.', $german ? '.' : ',');
    }
}
