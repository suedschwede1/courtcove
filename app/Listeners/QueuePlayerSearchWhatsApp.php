<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PlayerSearchStarted;
use App\Models\PlayerSearch;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\PlayerSearchOpenedNotification;
use App\Services\Notifications\RecipientResolver;
use App\Services\Notifications\Resolvers\PlayerSearchSubjectCheck;
use App\Services\Notifications\WhatsAppSettings;
use Throwable;

/**
 * Writes one pending outbox row per recipient of a new search (due in two minutes, one second apart in the
 * order of the resolver). Sends nothing itself and drains nothing: the dispatcher picks the rows up after the
 * debounce. Never throws; errors are reported so the booking and the exchange mail stay untouched.
 */
final class QueuePlayerSearchWhatsApp
{
    public function __construct(
        private readonly WhatsAppSettings $settings,
        private readonly RecipientResolver $recipients,
        private readonly WhatsAppChannel $channel,
        private readonly PlayerSearchSubjectCheck $subject,
    ) {}

    public function handle(PlayerSearchStarted $event): void
    {
        try {
            if (! $this->settings->enabled()) {
                return;
            }

            $search = PlayerSearch::query()
                ->with(['booking.reservations', 'booking.square', 'booking.user', 'signups'])
                ->find($event->searchId);
            if ($search === null || ! $search->isSeeking()) {
                return;
            }

            // Built once for every recipient; the outbox keeps none of it (the dispatcher rebuilds it).
            $content = $this->subject->openedContent($search, app()->getLocale());
            foreach ($this->recipients->forSearch($search)->values() as $position => $user) {
                $this->channel->send($user, new PlayerSearchOpenedNotification((int) $search->id, $position, $content));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
