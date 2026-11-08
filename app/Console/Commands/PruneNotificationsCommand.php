<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NotificationConsent;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSubscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Retention for the WhatsApp tables (daily): log rows after 12 months, revoked consents 3 years after the
 * revocation; for deleted or missing booking accounts the subscriptions go and the log loses its user link.
 * Only the account counts: a member record (anonymised or not) does not end an account's WhatsApp data.
 * The monthly usage table stays. Output: counters only.
 */
class PruneNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune';

    protected $description = 'Delete old WhatsApp log rows and revoked consents, unlink deleted users';

    public function handle(): int
    {
        $now = Carbon::now();
        $log = NotificationLogEntry::query()->where('created_at', '<', $now->copy()->subMonths(12))->delete();
        $consents = NotificationConsent::query()->whereNotNull('revoked_at')->where('revoked_at', '<', $now->copy()->subYears(3))->delete();

        $subscriptions = 0;
        $unlinked = 0;
        foreach ($this->goneUserIds() as $ids) {
            $subscriptions += NotificationSubscription::query()->whereIn('user_id', $ids)->delete();
            $unlinked += NotificationLogEntry::query()->whereIn('user_id', $ids)->update(['user_id' => null]);
        }

        $this->info(sprintf('log=%d consents=%d subscriptions=%d unlinked=%d', $log, $consents, $subscriptions, $unlinked));

        return self::SUCCESS;
    }

    /** @return \Generator<int, list<int>> chunks of ids of users that are deleted or gone */
    private function goneUserIds(): \Generator
    {
        $referenced = collect()
            ->merge(NotificationSubscription::query()->distinct()->pluck('user_id'))
            ->merge(NotificationLogEntry::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        foreach ($referenced->chunk(500) as $chunk) {
            $ids = $chunk->all();
            $present = User::query()->whereIn('uid', $ids)->where('status', '!=', 'deleted')->pluck('uid')->map(fn ($id): int => (int) $id)->all();
            $gone = array_values(array_diff($ids, $present));
            if ($gone !== []) {
                yield $gone;
            }
        }
    }
}
