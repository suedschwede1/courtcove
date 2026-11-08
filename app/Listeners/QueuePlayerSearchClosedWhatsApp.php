<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PlayerSearchClosed;
use App\Models\NotificationLogEntry;
use App\Models\PlayerSignup;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\PlayerSearchClosedNotification;
use App\Notifications\Types\NotificationTypes;
use App\Services\Notifications\WhatsAppSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * A search stopped seeking: its opened messages that are still pending are skipped at once (subject_gone).
 * With the follow-up type switched on (its type option, else `whatsapp.followup_closed`), every member
 * whose opened message actually went out gets one "search closed" follow-up (dedupe key per search and
 * member, due in five minutes), except members who joined the search themselves. Never throws.
 */
final class QueuePlayerSearchClosedWhatsApp
{
    private const CHUNK_SIZE = 50;

    public function __construct(
        private readonly WhatsAppSettings $settings,
        private readonly WhatsAppChannel $channel,
    ) {}

    public function handle(PlayerSearchClosed $event): void
    {
        try {
            $opened = fn () => NotificationLogEntry::query()
                ->where('type', NotificationTypes::PLAYER_SEARCH_OPENED)
                ->where('subject_type', 'player_search')
                ->where('subject_id', $event->searchId);

            $opened()->where('status', NotificationLogEntry::PENDING)->update([
                'status' => NotificationLogEntry::SKIPPED,
                'skip_reason' => NotificationLogEntry::SUBJECT_GONE,
                'updated_at' => Carbon::now(),
            ]);

            if (! $this->settings->enabled() || ! $this->settings->typeEnabled(NotificationTypes::PLAYER_SEARCH_CLOSED)) {
                return;
            }

            $userIds = $opened()
                ->whereIn('status', [NotificationLogEntry::SENT, NotificationLogEntry::DELIVERED, NotificationLogEntry::READ])
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id')
                ->all();
            // Members who joined this search know how it ended; they get no follow-up.
            $joined = PlayerSignup::query()->where('search_id', $event->searchId)->pluck('uid')->map(fn ($uid): int => (int) $uid)->all();
            $userIds = array_values(array_diff(array_map('intval', $userIds), $joined));
            if ($userIds === []) {
                return;
            }

            $notification = new PlayerSearchClosedNotification($event->searchId);
            User::query()->whereKey($userIds)->chunkById(self::CHUNK_SIZE, function (Collection $users) use ($notification): void {
                foreach ($users as $user) {
                    $this->channel->send($user, $notification);
                }
            }, 'uid');
        } catch (Throwable $e) {
            report($e);
        }
    }
}
