<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Notifications\WhatsAppSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the signature of a provider webhook: HMAC-SHA1 (key: auth token) over the public URL plus the
 * POST keys and values (raw body) sorted by key, base64, in the header named by `whatsapp.signature_header`. The URL
 * comes from the configured webhook base URL (fallback APP_URL) plus the request path, never from Host
 * headers, because the host runs behind a proxy. A missing header name, token or signature is a 403; the
 * log line names the reason only, never the payload.
 */
final class VerifyWebhookSignature
{
    public function __construct(private readonly WhatsAppSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $reason = $this->rejection($request);
        if ($reason !== null) {
            Log::warning('whatsapp webhook rejected', ['reason' => $reason, 'route' => $request->path()]);

            abort(403);
        }

        return $next($request);
    }

    private function rejection(Request $request): ?string
    {
        $header = $this->settings->signatureHeader();
        $token = $this->settings->authToken();
        if ($header === null || $token === null) {
            return 'not_configured';
        }

        $given = $request->header($header);
        if (! is_string($given) || $given === '') {
            return 'signature_missing';
        }

        return hash_equals($this->expected($request, $token), $given) ? null : 'signature_invalid';
    }

    private function expected(Request $request, string $token): string
    {
        $base = $this->settings->webhookBaseUrl() ?? (string) config('app.url');
        $data = rtrim($base, '/').$request->getRequestUri();

        // Raw body pairs: TrimStrings and ConvertEmptyStringsToNull change request->post(), the provider signed the original.
        $pairs = [];
        foreach (explode('&', (string) $request->getContent()) as $part) {
            if ($part === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $pairs[] = [urldecode($key), urldecode($value)];
        }
        // stable sort by key (byte order); repeated keys keep their order
        usort($pairs, static fn (array $x, array $y): int => strcmp($x[0], $y[0]));
        foreach ($pairs as [$key, $value]) {
            $data .= $key.$value;
        }

        return base64_encode(hash_hmac('sha1', $data, $token, true));
    }
}
