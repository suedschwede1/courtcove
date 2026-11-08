<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\NotificationLogEntry;
use App\Services\Notifications\ConsentService;
use App\Services\Notifications\WebhookReply;
use App\Services\Notifications\WhatsAppSettings;
use App\Support\Phone\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Provider webhooks (signature checked by VerifyWebhookSignature, no session, no CSRF):
 * delivery status of sent messages and inbound messages (STOP and friends). Replies are the response
 * body, not a send, so they cost no budget.
 */
final class WhatsAppWebhookController extends Controller
{
    private const STOP_WORDS = ['stop', 'stopp', 'abmelden', 'unsubscribe'];

    /** Statuses that may be replaced by the given one (a status never moves backwards). */
    private const FROM = [
        NotificationLogEntry::SENT => [NotificationLogEntry::SENDING],
        NotificationLogEntry::DELIVERED => [NotificationLogEntry::SENDING, NotificationLogEntry::SENT],
        NotificationLogEntry::READ => [NotificationLogEntry::SENDING, NotificationLogEntry::SENT, NotificationLogEntry::DELIVERED],
        NotificationLogEntry::FAILED => [NotificationLogEntry::SENDING, NotificationLogEntry::SENT],
        NotificationLogEntry::UNDELIVERED => [NotificationLogEntry::SENDING, NotificationLogEntry::SENT],
    ];

    public function __construct(private readonly ConsentService $consents, private readonly WhatsAppSettings $settings) {}

    public function status(Request $request): Response
    {
        $sid = $this->text($request, 'MessageSid');
        $target = strtolower($this->text($request, 'MessageStatus'));

        if ($sid !== '' && isset(self::FROM[$target])) {
            $this->apply($sid, $target, $this->text($request, 'ErrorCode'));
        }

        return new Response('', 200);
    }

    public function inbound(Request $request): Response
    {
        $e164 = $this->sender($this->text($request, 'From'));
        if ($e164 === null) {
            return WebhookReply::make(null);
        }

        // Public link from the configured base URL, not from the request host (proxy).
        $url = rtrim($this->settings->webhookBaseUrl() ?? (string) config('app.url'), '/').route('account.edit', [], false);
        $word = mb_strtolower(trim($this->text($request, 'Body')));

        if (in_array($word, self::STOP_WORDS, true)) {
            $this->consents->revokeByPhoneHash($this->consents->phoneHash($e164), 'stop');

            return WebhookReply::make(__('booking.notifications.webhook.stop_confirmed', ['url' => $url]));
        }

        if ($word === 'start') {
            return WebhookReply::make(__('booking.notifications.webhook.start', ['url' => $url]));
        }

        $key = 'whatsapp-help:'.$this->consents->phoneHash($e164).':'.Carbon::now('Europe/Vienna')->format('Y-m-d');
        if (! Cache::add($key, true, now()->addDay())) {
            return WebhookReply::make(null);
        }

        return WebhookReply::make(__('booking.notifications.webhook.help', ['url' => $url]));
    }

    /** One compare-and-set update, so a parallel dispatcher or webhook can never be overwritten backwards. */
    private function apply(string $sid, string $target, string $errorCode): void
    {
        $row = NotificationLogEntry::query()->where('channel', 'whatsapp')->where('provider_sid', $sid)->first();
        if ($row === null) {
            return;
        }

        $staleFailure = $row->status === NotificationLogEntry::FAILED && $row->error_code === 'stale'
            && in_array($target, [NotificationLogEntry::SENT, NotificationLogEntry::DELIVERED, NotificationLogEntry::READ], true);
        if (! $staleFailure && ! in_array($row->status, self::FROM[$target], true)) {
            return;
        }

        $values = ['status' => $target, 'error_code' => null];
        if (in_array($target, [NotificationLogEntry::FAILED, NotificationLogEntry::UNDELIVERED], true)) {
            $values['error_code'] = $errorCode === '' ? null : mb_substr($errorCode, 0, 16);
        }
        if (in_array($target, [NotificationLogEntry::DELIVERED, NotificationLogEntry::READ], true) && $row->delivered_at === null) {
            $values['delivered_at'] = Carbon::now();
        }

        NotificationLogEntry::query()
            ->whereKey($row->getKey())
            ->where('status', $row->status)
            ->update($values);
    }

    /** E.164 of the sender (`whatsapp:` prefix cut off), null when it cannot be parsed. */
    private function sender(string $from): ?string
    {
        $raw = trim(preg_replace('/^whatsapp:/i', '', trim($from)) ?? '');
        if ($raw === '') {
            return null;
        }

        $parsed = PhoneNumber::parse($raw);

        return $parsed->valid ? $parsed->e164 : null;
    }

    private function text(Request $request, string $key): string
    {
        $value = $request->post($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
