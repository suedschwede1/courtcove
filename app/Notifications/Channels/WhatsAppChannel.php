<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\NotificationLogEntry;
use App\Models\User;
use App\Notifications\Messages\WhatsAppMessage;
use App\Services\Notifications\ConsentService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Outbox channel: writes exactly one `pending` row per dedupe key and never calls the provider; the
 * NotificationDispatcher sends later. Never throws, errors are reported.
 */
final class WhatsAppChannel
{
    public function __construct(private readonly ConsentService $consents) {}

    public function send(object $notifiable, Notification $notification): void
    {
        try {
            if (! $notifiable instanceof User || ! method_exists($notification, 'toWhatsApp')) {
                return;
            }

            $message = $notification->toWhatsApp($notifiable);
            if (! $message instanceof WhatsAppMessage) {
                return;
            }

            $gate = $this->consents->gate($notifiable, $message->type->isAdminTest());
            $now = Carbon::now();

            DB::table('notification_log')->insertOrIgnore([
                'type' => $message->type->value,
                'channel' => ConsentService::CHANNEL,
                'user_id' => $notifiable->getKey(),
                'subject_type' => substr((string) $message->subjectType, 0, 32),
                'subject_id' => $message->subjectId ?? 0,
                'dedupe_key' => substr($message->dedupeKey, 0, 191),
                'status' => NotificationLogEntry::PENDING,
                'priority' => $message->type->priority(),
                'subject_payload' => $message->subjectPayload === [] ? null : json_encode($message->subjectPayload, JSON_THROW_ON_ERROR),
                'attempts' => 0,
                'not_before' => Carbon::instance($message->notBefore)->setTimezone(config('app.timezone')),
                'phone_last2' => $gate->last2,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
