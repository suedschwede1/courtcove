<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationConsent;
use App\Models\NotificationLogEntry;
use App\Models\User;
use App\Notifications\Types\NotificationType;
use App\Services\Notifications\Transport\TemplateRef;
use App\Services\Notifications\Transport\TransportFactory;
use App\Services\Notifications\Transport\TransportResult;
use App\Services\Notifications\Transport\WhatsAppTransport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the due rows of the WhatsApp outbox, one provider call at a time.
 *
 * Per row: (1) claim in a short transaction (pending and due, ordered by priority, slot, id; locked rows
 * of a parallel worker are skipped) and set `sending`; (2) re-check main switch/readiness, pause, subject,
 * consent, gate, number hash and the daily limit (Vienna day); (3) reserve the budget; (4) resolve the
 * template; (5) send, outside every transaction; (6) settle and store the SID. Retryable errors back off
 * 1, 5 and 15 minutes (never up to the subject deadline), permanent errors fail, an authentication error
 * pauses the channel and ends the run. `sending` rows older than five minutes are never sent again: their
 * outcome is unknown, so they fail as `stale`. Content is rebuilt from the subject at send time.
 */
final class NotificationDispatcher
{
    private const TIMEZONE = 'Europe/Vienna';

    private const STALE_MINUTES = 5;

    /** Wait before retry n (1-based); the row fails once all are used. */
    private const BACKOFF_MINUTES = [1, 5, 15];

    private const CLAIM_TRIES = 3;

    public function __construct(
        private readonly WhatsAppSettings $settings,
        private readonly ConsentService $consents,
        private readonly BudgetService $budget,
        private readonly TransportFactory $transports,
        private readonly SubjectResolverRegistry $resolvers,
    ) {}

    /** Works through at most `$max` rows (default: `max_per_run`) and stops after `$seconds`. */
    public function run(?int $max = null, int $seconds = 50): DispatchReport
    {
        $report = new DispatchReport;
        $max ??= $this->settings->maxPerRun();
        $started = microtime(true);

        $this->failStaleRows($report);

        if ($this->settings->pausedReason() !== null) {
            $report->stoppedReason = 'paused';

            return $report;
        }

        $transport = null;
        for ($done = 0; $done < $max; $done++) {
            if (microtime(true) - $started >= $seconds) {
                break;
            }

            $row = $this->claim();
            if ($row === null) {
                break;
            }

            $transport ??= $this->transports->make();
            if ($this->process($row, $transport, $report)) {
                break;
            }
        }

        return $report;
    }

    /** Short run for lazy drains and the admin test. */
    public function drain(int $max, int $seconds): DispatchReport
    {
        return $this->run(max(0, $max), $seconds);
    }

    /** Takes the next due row and marks it `sending`; null when nothing is due. */
    private function claim(): ?NotificationLogEntry
    {
        for ($try = 0; $try < self::CLAIM_TRIES; $try++) {
            $result = DB::transaction(function (): NotificationLogEntry|false|null {
                $query = NotificationLogEntry::query()
                    ->where('status', NotificationLogEntry::PENDING)
                    ->where('not_before', '<=', Carbon::now())
                    ->orderBy('priority')
                    ->orderBy('not_before')
                    ->orderBy('id')
                    ->limit(1);
                $row = $this->lockSkippingLocked($query)->first();
                if ($row === null) {
                    return null;
                }

                // Conditional update: even without SKIP LOCKED only one worker can win the row.
                $claimed = NotificationLogEntry::query()
                    ->whereKey($row->getKey())
                    ->where('status', NotificationLogEntry::PENDING)
                    ->update([
                        'status' => NotificationLogEntry::SENDING,
                        'cost_estimate_micros' => null,
                        'updated_at' => Carbon::now(),
                    ]);

                return $claimed === 1 ? $row->refresh() : false;
            });

            if ($result !== false) {
                return $result;
            }
        }

        return null;
    }

    /** @return bool true when the run must stop */
    private function process(NotificationLogEntry $row, WhatsAppTransport $transport, DispatchReport $report): bool
    {
        $reservation = null;

        try {
            if ($this->settings->readyToSend() !== null) {
                return $this->skip($row, NotificationLogEntry::DISABLED, $report);
            }
            if ($this->settings->pausedReason() !== null) {
                return $this->skip($row, NotificationLogEntry::PAUSED, $report);
            }

            $type = NotificationType::tryFrom($row->type);
            $resolver = $type === null ? null : $this->resolvers->for($type);
            $user = $row->user_id === null ? null : User::query()->find($row->user_id);
            if ($type === null || $resolver === null || $user === null || ! $resolver->stillRelevant($row)) {
                return $this->skip($row, NotificationLogEntry::SUBJECT_GONE, $report);
            }

            $purpose = $type->purpose();
            $consent = $purpose === null ? null : $this->consents->activeConsent($user, $purpose);
            if ($purpose !== null && $consent === null) {
                return $this->skip($row, NotificationLogEntry::NO_CONSENT, $report);
            }

            $gate = $this->consents->gate($user, $type->isAdminTest());
            if (! $gate->passed || $gate->e164 === null) {
                return $this->skip($row, NotificationLogEntry::GATE, $report);
            }

            if ($consent !== null && ! $this->consents->matchesCurrentPhone($consent, $user)) {
                // The number changed without the hook (mass update): the consent belongs to the old number.
                $this->consents->revoke($user, 'phone_changed');

                return $this->skip($row, NotificationLogEntry::PHONE_CHANGED, $report);
            }

            if (! $type->isAdminTest() && $this->sentToday($user, $row) >= $this->settings->dailyLimitPerUser()) {
                return $this->skip($row, NotificationLogEntry::DAILY_LIMIT, $report);
            }

            $price = $this->settings->priceEstimateMicros();
            $reservation = $this->budget->reserve($price);
            if ($reservation === null) {
                $this->sendBudgetAlerts();

                return $this->skip($row, NotificationLogEntry::BUDGET, $report);
            }
            $row->forceFill(['cost_estimate_micros' => $price, 'phone_last2' => $gate->last2])->save();

            $locale = $this->locale($consent);
            $contentId = $this->settings->templateId($type->templateKey(), $locale);
            if ($contentId === null && ! $this->settings->sandbox()) {
                $this->release($reservation);
                $reservation = null;
                Log::warning('whatsapp template missing', ['type' => $type->templateKey(), 'locale' => $locale]);

                return $this->skip($row, NotificationLogEntry::TEMPLATE_MISSING, $report, ['cost_estimate_micros' => null]);
            }

            $content = $resolver->build($row, $user, $locale);
            $result = $transport->send(
                $gate->e164,
                new TemplateRef($contentId, $type->templateKey(), $locale, $content->freeText),
                $content->variables,
                $content->buttonSuffix,
            );

            $this->budget->settle($reservation, $result->accepted);
            $reservation = null;
            $this->sendBudgetAlerts();

            return $this->record($row, $result, $resolver, $report);
        } catch (Throwable $e) {
            if ($reservation !== null) {
                $this->release($reservation);
            }
            report($e);
            $this->update($row, ['status' => NotificationLogEntry::FAILED, 'error_code' => 'exception', 'cost_estimate_micros' => null]);
            $report->failed++;

            return false;
        }
    }

    /** Stores the transport outcome; true when the run must stop. */
    private function record(NotificationLogEntry $row, TransportResult $result, SubjectResolver $resolver, DispatchReport $report): bool
    {
        if ($result->accepted) {
            $row->forceFill([
                'status' => NotificationLogEntry::SENT,
                'provider_sid' => $result->sid,
                'sent_at' => Carbon::now(),
                'error_code' => null,
            ])->save();
            $report->sent++;

            return false;
        }

        $code = substr((string) ($result->errorCode ?? 'unknown'), 0, 16);

        if ($result->authFailure) {
            // Nothing went out: the row waits for "resume" instead of using up its attempts.
            $row->forceFill(['status' => NotificationLogEntry::PENDING, 'error_code' => $code, 'cost_estimate_micros' => null])->save();
            $this->settings->pause('auth_failed');
            $report->stoppedReason = 'auth_failed';

            return true;
        }

        if ($result->retryable && $row->attempts < count(self::BACKOFF_MINUTES)) {
            $next = Carbon::now()->addMinutes(self::BACKOFF_MINUTES[$row->attempts]);
            $deadline = $resolver instanceof SubjectDeadline ? $resolver->deadline($row) : null;
            if ($deadline === null || $next->lt($deadline)) {
                $row->forceFill([
                    'status' => NotificationLogEntry::PENDING,
                    'attempts' => $row->attempts + 1,
                    'not_before' => $next,
                    'error_code' => $code,
                    'cost_estimate_micros' => null,
                ])->save();
                $report->retried++;

                return false;
            }
        }

        $row->forceFill(['status' => NotificationLogEntry::FAILED, 'error_code' => $code, 'cost_estimate_micros' => null])->save();
        $report->failed++;

        return false;
    }

    /**
     * `sending` rows whose claim is older than five minutes: the worker died during or after the provider
     * call, so a resend could duplicate the message. They fail as `stale`; a status webhook that resolved
     * the row has already moved it out of `sending`. A held reservation is settled as accepted (cost kept).
     */
    private function failStaleRows(DispatchReport $report): void
    {
        $cutoff = Carbon::now()->subMinutes(self::STALE_MINUTES);
        $ids = NotificationLogEntry::query()
            ->where('status', NotificationLogEntry::SENDING)
            ->where('updated_at', '<', $cutoff)
            ->pluck('id');

        foreach ($ids as $id) {
            try {
                DB::transaction(function () use ($id, $cutoff, $report): void {
                    $query = NotificationLogEntry::query()
                        ->whereKey($id)
                        ->where('status', NotificationLogEntry::SENDING)
                        ->where('updated_at', '<', $cutoff);
                    $row = $this->lockSkippingLocked($query)->first();
                    if ($row === null) {
                        return;
                    }

                    $claimedAt = $row->updated_at instanceof Carbon ? $row->updated_at->copy() : Carbon::now();
                    $cost = $row->cost_estimate_micros;
                    $row->forceFill(['status' => NotificationLogEntry::FAILED, 'error_code' => 'stale'])->save();
                    if ($cost !== null) {
                        $month = $claimedAt->setTimezone(self::TIMEZONE)->format('Y-m');
                        $this->budget->settle(new BudgetReservation($month, $cost), true);
                    }
                    $report->stale++;
                });
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** Messages of the user on the current Vienna day that went out or are going out (not the admin test). */
    private function sentToday(User $user, NotificationLogEntry $current): int
    {
        $start = Carbon::now(self::TIMEZONE)->startOfDay()->setTimezone((string) config('app.timezone'));
        $end = $start->copy()->addDay();

        return NotificationLogEntry::query()
            ->where('user_id', $user->getKey())
            ->whereKeyNot($current->getKey())
            ->where('type', '!=', NotificationType::AdminTest->value)
            ->where(function (Builder $query) use ($start, $end): void {
                $query->where(function (Builder $q) use ($start, $end): void {
                    $q->whereIn('status', [NotificationLogEntry::SENT, NotificationLogEntry::DELIVERED, NotificationLogEntry::READ])
                        ->where('sent_at', '>=', $start)
                        ->where('sent_at', '<', $end);
                })->orWhere(function (Builder $q) use ($start, $end): void {
                    $q->where('status', NotificationLogEntry::SENDING)
                        ->where('updated_at', '>=', $start)
                        ->where('updated_at', '<', $end);
                });
            })
            ->count();
    }

    private function locale(?NotificationConsent $consent): string
    {
        $locale = strtolower(substr((string) $consent?->locale, 0, 2));

        return in_array($locale, ['de', 'en'], true) ? $locale : $this->settings->defaultLocale();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function skip(NotificationLogEntry $row, string $reason, DispatchReport $report, array $extra = []): bool
    {
        $this->update($row, ['status' => NotificationLogEntry::SKIPPED, 'skip_reason' => $reason] + $extra);
        $report->skip($reason);

        return false;
    }

    /** @param  array<string, mixed>  $values */
    private function update(NotificationLogEntry $row, array $values): void
    {
        try {
            $row->forceFill($values)->save();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function release(BudgetReservation $reservation): void
    {
        try {
            $this->budget->settle($reservation, false);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function sendBudgetAlerts(): void
    {
        try {
            $this->budget->sendAlertsIfNeeded();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Row lock that skips rows a parallel worker holds (MySQL 8 / MariaDB 10.6+); SQLite has no row locks.
     *
     * @param  Builder<NotificationLogEntry>  $query
     * @return Builder<NotificationLogEntry>
     */
    private function lockSkippingLocked(Builder $query): Builder
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)
            ? $query->lock('for update skip locked')
            : $query->lockForUpdate();
    }
}
