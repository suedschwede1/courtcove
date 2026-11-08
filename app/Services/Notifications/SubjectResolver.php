<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationLogEntry;
use App\Models\User;
use App\Notifications\Registry\NotificationTypeDefinition;

/**
 * Per notification type: decides at send time whether the subject still matters and rebuilds the
 * message content from the IDs in `subject_payload`. Named in its {@see NotificationTypeDefinition}.
 */
interface SubjectResolver
{
    public function stillRelevant(NotificationLogEntry $row): bool;

    public function build(NotificationLogEntry $row, User $user, string $locale): MessageContent;
}
