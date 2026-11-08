<?php

declare(strict_types=1);

namespace App\Services\Notifications\Transport;

use App\Services\Notifications\WhatsAppSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Live driver over the provider's REST API (Laravel HTTP client, no SDK). The base URL and all endpoint
 * paths come from the settings (.env or option), nothing is hard coded. Paths may contain the
 * placeholders `{account}` and `{service}`. Logs and results never carry secrets or message data; the
 * account ID shows up only as its last four characters.
 */
final class ProviderTransport implements WhatsAppTransport
{
    private const TIMEOUT = 10;

    private const CONNECT_TIMEOUT = 5;

    /**
     * The single mapping from provider error codes to outcomes. Key: provider error code, or
     * `http_429` / `http_5xx` / `timeout` for transport level problems. Value: true = retryable,
     * false = permanent. Unknown codes are permanent. HTTP 401/403 are handled as `authFailure`
     * before this table. Check the list against the current provider documentation.
     *
     * @var array<int|string, bool>
     */
    public const ERROR_MAP = [
        '21211' => false, // invalid recipient number
        '21214' => false, // recipient number cannot be reached
        '21408' => false, // region not enabled
        '21610' => false, // recipient opted out
        '21614' => false, // recipient is not a mobile number
        '63003' => false, // channel could not find the recipient
        '63015' => false, // sandbox recipient has not joined
        '63016' => false, // outside the allowed session window / template required
        '63024' => false, // invalid message recipient
        '63049' => false, // message blocked by the channel
        '20429' => true,  // too many requests
        '63018' => true,  // channel rate limit exceeded
        'http_429' => true,
        'http_5xx' => true,
        'timeout' => true,
    ];

    public function __construct(private readonly WhatsAppSettings $settings) {}

    public function send(string $toE164, TemplateRef $template, array $vars, ?string $buttonSuffix): TransportResult
    {
        $url = $this->url($this->settings->apiMessagesPath());
        if ($url === null) {
            return TransportResult::failed('config_missing');
        }

        $fields = ['To' => 'whatsapp:'.$toE164, 'StatusCallback' => $this->statusCallbackUrl()];

        if ($this->settings->sandbox()) {
            $fields['From'] = $this->prefixed($this->settings->sandboxSender());
            $fields['Body'] = $template->freeText;
        } else {
            $service = $this->settings->messagingServiceSid();
            if ($service !== null) {
                $fields['MessagingServiceSid'] = $service;
            } else {
                $fields['From'] = $this->prefixed($this->settings->sender());
            }
            if ($template->contentId !== null) {
                $fields['ContentSid'] = $template->contentId;
            }
            $fields['ContentVariables'] = json_encode((object) $this->variables($vars, $buttonSuffix), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        try {
            $response = $this->request()->asForm()->post($url, $fields);
        } catch (ConnectionException) {
            return $this->failure('timeout', $template->key);
        } catch (Throwable $e) {
            return $this->failure('transport_error', $template->key, $e);
        }

        if ($response->successful()) {
            $sid = $response->json('sid');

            return is_string($sid) && $sid !== '' ? TransportResult::accepted($sid) : $this->failure('no_sid', $template->key);
        }

        return $this->mapFailure($response, $template->key);
    }

    public function probe(): ProbeResult
    {
        $paths = [$this->settings->apiAccountPath()];
        if ($this->settings->messagingServiceSid() !== null) {
            $paths[] = $this->settings->apiServicePath();
        }

        foreach ($paths as $path) {
            $url = $this->url($path);
            if ($url === null) {
                return new ProbeResult(false, 'config_missing');
            }

            try {
                $response = $this->request()->get($url);
            } catch (ConnectionException) {
                return new ProbeResult(false, 'timeout');
            } catch (Throwable) {
                return new ProbeResult(false, 'transport_error');
            }

            if (! $response->successful()) {
                return new ProbeResult(false, 'http_'.$response->status());
            }
        }

        return new ProbeResult(true, 'ok');
    }

    private function request(): PendingRequest
    {
        return Http::withBasicAuth((string) $this->settings->accountSid(), (string) $this->settings->authToken())
            ->timeout(self::TIMEOUT)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->acceptJson();
    }

    private function url(?string $path): ?string
    {
        $base = $this->settings->apiBase();
        if ($base === null || $path === null) {
            return null;
        }

        $path = strtr($path, [
            '{account}' => rawurlencode((string) $this->settings->accountSid()),
            '{service}' => rawurlencode((string) $this->settings->messagingServiceSid()),
        ]);

        return rtrim($base, '/').'/'.ltrim($path, '/');
    }

    private function statusCallbackUrl(): string
    {
        $base = $this->settings->webhookBaseUrl() ?? (string) config('app.url');

        return rtrim($base, '/').'/webhooks/whatsapp/status';
    }

    private function prefixed(?string $number): string
    {
        $number = (string) $number;

        return str_starts_with($number, 'whatsapp:') ? $number : 'whatsapp:'.$number;
    }

    /**
     * @param  array<int|string, string>  $vars
     * @return array<string, string>
     */
    private function variables(array $vars, ?string $buttonSuffix): array
    {
        $out = [];
        foreach ($vars as $key => $value) {
            $out[(string) $key] = $value;
        }
        if ($buttonSuffix !== null) {
            $out[(string) (count($out) + 1)] = $buttonSuffix;
        }

        return $out;
    }

    private function mapFailure(Response $response, string $key): TransportResult
    {
        $status = $response->status();
        if ($status === 401 || $status === 403) {
            $this->logFailure('http_'.$status, $key);

            return TransportResult::failed('http_'.$status, false, true);
        }

        $code = $response->json('code');
        $code = is_int($code) || (is_string($code) && ctype_digit($code)) ? (string) $code : null;
        if ($code !== null && array_key_exists($code, self::ERROR_MAP)) {
            return $this->failure($code, $key, null, self::ERROR_MAP[$code]);
        }

        if ($status === 429) {
            return $this->failure($code ?? 'http_429', $key, null, self::ERROR_MAP['http_429']);
        }
        if ($status >= 500) {
            return $this->failure($code ?? 'http_'.$status, $key, null, self::ERROR_MAP['http_5xx']);
        }

        return $this->failure($code ?? 'http_'.$status, $key);
    }

    private function failure(string $code, string $key, ?Throwable $e = null, ?bool $retryable = null): TransportResult
    {
        $retryable ??= self::ERROR_MAP[$code] ?? false;
        $this->logFailure($code, $key, $e);

        return TransportResult::failed($code, $retryable);
    }

    private function logFailure(string $code, string $key, ?Throwable $e = null): void
    {
        $account = (string) $this->settings->accountSid();
        Log::warning('whatsapp transport failure', array_filter([
            'type' => $key,
            'code' => $code,
            'account' => $account === '' ? null : '…'.substr($account, -4),
            'exception' => $e === null ? null : $e::class,
        ]));
    }
}
