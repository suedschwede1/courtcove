<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PlayerSearch;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppMessage;
use App\Notifications\Types\NotificationType;
use App\Services\Notifications\MessageContent;
use App\Services\Notifications\Resolvers\PlayerSearchSubjectCheck;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * WhatsApp message of a new player search to one member. Due two minutes after the search opened (debounce),
 * plus `$delaySeconds` so the members with the fewest messages this month go first. Only IDs reach the outbox;
 * the variables are rebuilt by {@see PlayerSearchSubjectCheck} at send time. The exchange mail is separate
 * and unchanged.
 */
final class PlayerSearchOpenedNotification extends Notification
{
    public const DEBOUNCE_MINUTES = 2;

    /**
     * @param  MessageContent|null  $content  prebuilt once per search by the listener (not stored)
     * @param  int|null  $bid  booking of the search, passed by the listener so no recipient reads the search again
     */
    public function __construct(
        public readonly int $searchId,
        public readonly int $delaySeconds = 0,
        private readonly ?MessageContent $content = null,
        private readonly ?int $bid = null,
    ) {}

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [WhatsAppChannel::class];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $userId = $notifiable instanceof User ? (int) $notifiable->getKey() : 0;
        $search = $this->content !== null && $this->bid !== null ? null : PlayerSearch::query()->find($this->searchId, ['id', 'bid']);
        $content = $this->content ?? $this->contentFor($search);
        $bid = $this->bid ?? ($search !== null ? (int) $search->bid : null);

        return new WhatsAppMessage(
            type: NotificationType::PlayerSearchOpened,
            variables: $content->variables ?? [],
            buttonSuffix: $content?->buttonSuffix,
            dedupeKey: NotificationType::PlayerSearchOpened->value.':'.$this->searchId.':'.$userId.':whatsapp',
            subjectType: 'player_search',
            subjectId: $this->searchId,
            notBefore: Carbon::now()->addMinutes(self::DEBOUNCE_MINUTES)->addSeconds(max(0, $this->delaySeconds)),
            subjectPayload: ['search_id' => $this->searchId, 'bid' => $bid],
        );
    }

    private function contentFor(?PlayerSearch $search): ?MessageContent
    {
        if ($search === null) {
            return null;
        }

        $search = PlayerSearch::query()->with(['booking.reservations', 'booking.square', 'booking.user'])->find($search->id);

        return $search === null ? null : app(PlayerSearchSubjectCheck::class)->openedContent($search, app()->getLocale());
    }
}
