<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Support\StagingDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * Staging mail protection, applied centrally to every message the mailer is about to send
 * (mailables, notifications, queued mail, Mail::raw): on a staging database all recipients are
 * replaced by MAIL_STAGING_REDIRECT (mail.staging_redirect); with a real transport and no usable
 * redirect address the message is not sent at all. A non-staging database is never touched.
 */
class StagingMailGuard
{
    /** Transports that cannot deliver to anyone. */
    private const SAFE_TRANSPORTS = ['log', 'array'];

    private const SHORT_LIST = 2;

    /** Returning false stops the send (Mailer::shouldSendMessage). */
    public function handle(MessageSending $event): ?bool
    {
        if (! StagingDatabase::active()) {
            return null;
        }

        $mailer = $event->data['mailer'] ?? null;
        $mailer = is_string($mailer) ? $mailer : null;
        $redirect = self::redirectAddress();

        if ($redirect === null) {
            if (! self::isRealMailer($mailer)) {
                return null;
            }

            Log::warning('Staging mail not sent: MAIL_STAGING_REDIRECT is not set or not a valid address.', [
                'mailer' => $mailer,
                'recipient_count' => count($event->message->getTo()) + count($event->message->getCc()) + count($event->message->getBcc()),
            ]);

            return false;
        }

        $this->redirect($event, $redirect);

        return null;
    }

    /** The configured redirect address, or null when empty or not exactly one valid address. */
    public static function redirectAddress(): ?string
    {
        $value = config('mail.staging_redirect');

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || strlen($value) > 254 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $value;
    }

    /** A mailer is real unless it is log/array (or a failover/round-robin of only those); unknown counts as real. */
    public static function isRealMailer(?string $name, int $depth = 0): bool
    {
        if ($name === null || $name === '' || $depth > 5) {
            return true;
        }

        $transport = config("mail.mailers.{$name}.transport");

        if (! is_string($transport)) {
            return true;
        }

        if (in_array($transport, self::SAFE_TRANSPORTS, true)) {
            return false;
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $inner = config("mail.mailers.{$name}.mailers");

            if (! is_array($inner) || $inner === []) {
                return true;
            }

            foreach ($inner as $child) {
                if (! is_string($child) || self::isRealMailer($child, $depth + 1)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    private function redirect(MessageSending $event, string $redirect): void
    {
        $message = $event->message;
        $headers = $message->getHeaders();

        $to = $this->addresses($message->getTo());
        $cc = $this->addresses($message->getCc());
        $bcc = $this->addresses($message->getBcc());
        $originals = array_values(array_unique([...$to, ...$cc, ...$bcc]));

        foreach (['X-Original-To', 'X-Original-Cc', 'X-Original-Bcc', 'Cc', 'Bcc'] as $name) {
            $headers->remove($name);
        }

        $message->to(new Address($redirect));

        foreach (['X-Original-To' => $to, 'X-Original-Cc' => $cc, 'X-Original-Bcc' => $bcc] as $name => $list) {
            if ($list !== []) {
                $headers->addTextHeader($name, implode(', ', $list));
            }
        }

        $shown = array_slice($originals, 0, self::SHORT_LIST);
        $more = count($originals) - count($shown);
        $label = implode(', ', $shown).($more > 0 ? " +{$more}" : '');
        $label = (string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $label);

        $message->subject("[STAGING → {$label}] ".(string) $message->getSubject());
    }

    /**
     * @param  array<Address>  $list
     * @return list<string>
     */
    private function addresses(array $list): array
    {
        return array_values(array_map(fn (Address $address): string => $address->getAddress(), $list));
    }
}
