<?php

declare(strict_types=1);

namespace App\Services\Notifications\Resolvers;

use App\Models\NotificationLogEntry;
use App\Models\User;
use App\Services\Notifications\MessageContent;
use App\Services\Notifications\SubjectResolver;

/** The admin test message: always relevant while the admin exists, fixed text without variables. */
final class AdminTestResolver implements SubjectResolver
{
    public function stillRelevant(NotificationLogEntry $row): bool
    {
        return $row->user_id !== null;
    }

    public function build(NotificationLogEntry $row, User $user, string $locale): MessageContent
    {
        return new MessageContent(
            variables: [],
            buttonSuffix: null,
            freeText: (string) trans('booking.notifications.whatsapp.test', [], $locale),
        );
    }
}
