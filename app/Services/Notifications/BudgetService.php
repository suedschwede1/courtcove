<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Mail\WhatsAppBudgetAlert;
use App\Models\NotificationUsageMonthly;
use App\Services\BookingService;
use App\Support\Mail\SystemMailSender;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Monthly cost guard of the WhatsApp channel (month in Europe/Vienna). A message first reserves its estimated
 * price on the month row under a row lock, so parallel workers can never pass the budget; settle() keeps the
 * cost for an accepted message and gives it back for a rejected one. There is no reserve and no priority.
 * `estimated_micros` holds settled plus reserved cost.
 */
final class BudgetService
{
    public const CHANNEL = 'whatsapp';

    private const TIMEZONE = 'Europe/Vienna';

    public function __construct(
        private readonly WhatsAppSettings $settings,
        private readonly BookingService $bookings,
        private readonly SystemMailSender $mailSender,
    ) {}

    /** Holds `$micros` of the budget; null when the budget is 0 or would be exceeded. */
    public function reserve(int $micros): ?BudgetReservation
    {
        $budget = $this->settings->budgetMonthlyMicros();
        if ($budget <= 0 || $micros < 0) {
            return null;
        }

        $month = $this->month();

        return DB::transaction(function () use ($month, $micros, $budget): ?BudgetReservation {
            $row = $this->lockedRow($month);
            if ($row->estimated_micros + $micros > $budget) {
                return null;
            }

            $row->messages_reserved++;
            $row->estimated_micros += $micros;
            $row->save();

            return new BudgetReservation($month, $micros);
        });
    }

    /** An accepted message (provider SID received) keeps its cost; a rejected one counts 0. */
    public function settle(BudgetReservation $reservation, bool $accepted): void
    {
        DB::transaction(function () use ($reservation, $accepted): void {
            $row = $this->lockedRow($reservation->month);
            $row->messages_reserved = max(0, $row->messages_reserved - 1);
            if ($accepted) {
                $row->messages_sent++;
            } else {
                $row->estimated_micros = max(0, $row->estimated_micros - $reservation->micros);
            }
            $row->save();
        });
    }

    public function current(): BudgetSnapshot
    {
        $month = $this->month();
        $row = $this->find($month);
        $budget = $this->settings->budgetMonthlyMicros();
        $estimated = $row->estimated_micros ?? 0;

        $previous = $this->find($this->month(Carbon::now(self::TIMEZONE)->startOfMonth()->subMonth()));

        return new BudgetSnapshot(
            month: $month,
            messages: $row->messages_sent ?? 0,
            estimatedMicros: $estimated,
            budgetMicros: $budget,
            percent: $budget > 0 ? intdiv($estimated * 100, $budget) : 0,
            previousMonth: $previous === null ? null : [
                'messages' => $previous->messages_sent,
                'estimatedMicros' => $previous->estimated_micros,
            ],
        );
    }

    /** `stopped` = nothing more fits (budget 0, spent, or the next estimated message would not fit), `warn` from 80 %. */
    public function thresholdState(): string
    {
        $snap = $this->current();

        if ($snap->budgetMicros <= 0 || $this->stopped($snap)) {
            return 'stopped';
        }

        return $snap->percent >= 80 ? 'warn' : 'ok';
    }

    /**
     * One mail at 80 % and one at 100 % per month. Nothing is claimed while no recipient or no working mail
     * transport exists, so a later fix still alerts. A mail error never reaches the caller.
     */
    public function sendAlertsIfNeeded(): void
    {
        $snap = $this->current();
        if ($snap->budgetMicros <= 0 || $snap->percent < 80 && ! $this->stopped($snap)) {
            return;
        }

        $recipient = $this->recipient();
        if ($recipient === null || ! $this->bookings->isMailConfigured()) {
            return;
        }

        $level = $this->stopped($snap) ? 100 : 80;
        $claimed = DB::transaction(function () use ($snap, $level): bool {
            $row = $this->lockedRow($snap->month);
            if ($level === 100 ? $row->warned_100_at !== null : $row->warned_80_at !== null) {
                return false;
            }
            $now = Carbon::now(self::TIMEZONE);
            $row->warned_80_at ??= $now;
            if ($level === 100) {
                $row->warned_100_at = $now;
            }
            $row->save();

            return true;
        });
        if (! $claimed) {
            return;
        }

        try {
            Mail::to($recipient)->send(new WhatsAppBudgetAlert($snap, $level));
        } catch (\Throwable $e) {
            Log::warning('whatsapp budget alert mail failed', ['level' => $level, 'error' => $e::class]);
        }
    }

    private function stopped(BudgetSnapshot $snap): bool
    {
        if ($snap->budgetMicros <= 0) {
            return false;
        }
        $price = $this->settings->priceEstimateMicros();

        return $snap->estimatedMicros >= $snap->budgetMicros
            || ($price > 0 && $snap->estimatedMicros + $price > $snap->budgetMicros);
    }

    private function recipient(): ?string
    {
        $configured = $this->settings->budgetAlertEmail();
        if ($configured !== null && filter_var($configured, FILTER_VALIDATE_EMAIL) !== false) {
            return $configured;
        }

        $replyTo = $this->mailSender->replyTo(SystemMailSender::SCOPE_BOOKING)[0] ?? null;

        return $replyTo?->address;
    }

    private function month(?Carbon $at = null): string
    {
        return ($at ?? Carbon::now(self::TIMEZONE))->setTimezone(self::TIMEZONE)->format('Y-m');
    }

    private function find(string $month): ?NotificationUsageMonthly
    {
        return NotificationUsageMonthly::where('month', $month)->where('channel', self::CHANNEL)->first();
    }

    /** The month row under a write lock; created on first use (insert-or-ignore, safe against a parallel create). */
    private function lockedRow(string $month): NotificationUsageMonthly
    {
        DB::table('notification_usage_monthly')->insertOrIgnore([
            'month' => $month, 'channel' => self::CHANNEL,
        ]);

        return NotificationUsageMonthly::where('month', $month)
            ->where('channel', self::CHANNEL)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
