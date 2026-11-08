<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppMessage;
use App\Notifications\Types\NotificationType;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Test message of the admin page to the admin's own number; due at once. */
final class AdminTestNotification extends Notification
{
    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [WhatsAppChannel::class];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $userId = $notifiable instanceof User ? (int) $notifiable->getKey() : 0;

        return new WhatsAppMessage(
            type: NotificationType::AdminTest,
            variables: [],
            buttonSuffix: null,
            dedupeKey: NotificationType::AdminTest->value.':'.$userId.':'.Str::uuid()->toString(),
            subjectType: 'user',
            subjectId: $userId,
            notBefore: Carbon::now(),
        );
    }
}
