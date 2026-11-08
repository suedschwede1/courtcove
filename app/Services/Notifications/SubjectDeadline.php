<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationLogEntry;
use Carbon\CarbonInterface;

/**
 * Optional addition to a {@see SubjectResolver}: the time after which the subject is worthless (e.g. the
 * search deadline). A retry is never scheduled at or after it; the row fails instead.
 */
interface SubjectDeadline
{
    public function deadline(NotificationLogEntry $row): ?CarbonInterface;
}
