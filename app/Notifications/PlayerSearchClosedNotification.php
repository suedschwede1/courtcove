<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppMessage;
use App\Notifications\Types\NotificationType;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Optional follow-up "search closed" (option `whatsapp.followup_closed`), one per member and search, due five
 * minutes later so a search that opens again in between sends nothing. Variables are rebuilt at send time.
 */
final class PlayerSearchClosedNotification extends Notification
{
    public const DEBOUNCE_MINUTES = 5;

    public function __construct(public readonly int $searchId) {}

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [WhatsAppChannel::class];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $userId = $notifiable instanceof User ? (int) $notifiable->getKey() : 0;

        return new WhatsAppMessage(
            type: NotificationType::PlayerSearchClosed,
            variables: [],
            buttonSuffix: null,
            dedupeKey: NotificationType::PlayerSearchClosed->value.':'.$this->searchId.':'.$userId.':whatsapp',
            subjectType: 'player_search',
            subjectId: $this->searchId,
            notBefore: Carbon::now()->addMinutes(self::DEBOUNCE_MINUTES),
            subjectPayload: ['search_id' => $this->searchId],
        );
    }
}
