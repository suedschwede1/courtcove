<?php

declare(strict_types=1);

namespace Tests\Support;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

/** A "real" (not log/array) transport for tests: keeps what would have gone out instead of sending it. */
class CaptureMailTransport extends AbstractTransport
{
    /** @var list<Email> */
    public static array $sent = [];

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        if ($original instanceof Email) {
            self::$sent[] = $original;
        }
    }

    public function __toString(): string
    {
        return 'capture://';
    }
}
