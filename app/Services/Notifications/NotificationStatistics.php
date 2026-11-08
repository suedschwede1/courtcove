<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationLogEntry;
use App\Notifications\Registry\NotificationRegistry;
use Illuminate\Support\Carbon;

/**
 * Per-type figures of the WhatsApp outbox, read from `notification_log` (no extra table): messages that went
 * out in the current month (Europe/Vienna) and in the last 30 days, split by their current status (sent,
 * delivered, read), and the time of the last one. Failed, undelivered, skipped and pending rows do not count.
 */
final class NotificationStatistics
{
    private const TIMEZONE = 'Europe/Vienna';

    private const STATUSES = [NotificationLogEntry::SENT, NotificationLogEntry::DELIVERED, NotificationLogEntry::READ];

    public function __construct(private readonly NotificationRegistry $registry) {}

    /**
     * @return array{month: array{sent: int, delivered: int, read: int, total: int}, last30: array{sent: int, delivered: int, read: int, total: int}, last_sent_at: ?Carbon}
     */
    public function forType(string $type): array
    {
        return $this->collect([$type])[$type];
    }

    /**
     * Figures of every registered type, in registration order.
     *
     * @return array<string, array{month: array{sent: int, delivered: int, read: int, total: int}, last30: array{sent: int, delivered: int, read: int, total: int}, last_sent_at: ?Carbon}>
     */
    public function forTypes(): array
    {
        return $this->collect(array_keys($this->registry->types()));
    }

    /**
     * @param  list<string>  $types
     * @return array<string, array{month: array{sent: int, delivered: int, read: int, total: int}, last30: array{sent: int, delivered: int, read: int, total: int}, last_sent_at: ?Carbon}>
     */
    private function collect(array $types): array
    {
        $appZone = (string) config('app.timezone');
        $monthStart = Carbon::now(self::TIMEZONE)->startOfMonth()->setTimezone($appZone);
        $last30Start = Carbon::now()->subDays(30);
        $since = $monthStart->lt($last30Start) ? $monthStart : $last30Start;

        $empty = ['sent' => 0, 'delivered' => 0, 'read' => 0, 'total' => 0];
        $result = [];
        foreach ($types as $type) {
            $result[$type] = ['month' => $empty, 'last30' => $empty, 'last_sent_at' => null];
        }

        $rows = NotificationLogEntry::query()
            ->toBase()
            ->whereIn('type', $types)
            ->whereIn('status', self::STATUSES)
            ->where('sent_at', '>=', $since)
            ->groupBy('type', 'status')
            ->selectRaw(
                'type, status, sum(case when sent_at >= ? then 1 else 0 end) as in_month, sum(case when sent_at >= ? then 1 else 0 end) as in_last30',
                [$monthStart, $last30Start],
            )
            ->get();

        foreach ($rows as $row) {
            $type = (string) $row->type;
            $status = (string) $row->status;
            if (! isset($result[$type], $empty[$status])) {
                continue;
            }
            foreach (['month' => (int) $row->in_month, 'last30' => (int) $row->in_last30] as $window => $count) {
                $result[$type][$window][$status] += $count;
                $result[$type][$window]['total'] += $count;
            }
        }

        // The last message may be older than both windows.
        $last = NotificationLogEntry::query()
            ->whereIn('type', $types)
            ->whereIn('status', self::STATUSES)
            ->whereNotNull('sent_at')
            ->groupBy('type')
            ->selectRaw('type, max(sent_at) as last_sent_at')
            ->pluck('last_sent_at', 'type');
        foreach ($last as $type => $at) {
            if (isset($result[$type]) && $at !== null) {
                $result[$type]['last_sent_at'] = Carbon::parse((string) $at, $appZone);
            }
        }

        return $result;
    }
}
