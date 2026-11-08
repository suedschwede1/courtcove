<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use Illuminate\Http\Response;

/**
 * Wire format of the answer to an inbound message: a small XML document in the response body
 * (`<Response><Message>text</Message></Response>`, empty `<Response/>` for no reply). The only
 * place that knows the format.
 */
final class WebhookReply
{
    public static function make(?string $text): Response
    {
        $body = $text === null || $text === ''
            ? '<Response/>'
            : '<Response><Message>'.htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Message></Response>';

        return new Response('<?xml version="1.0" encoding="UTF-8"?>'.$body, 200, ['Content-Type' => 'text/xml; charset=UTF-8']);
    }
}
