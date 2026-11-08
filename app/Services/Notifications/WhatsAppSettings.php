<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Option;
use App\Notifications\Registry\NotificationRegistry;
use App\Support\StagingDatabase;
use BackedEnum;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Settings of the WhatsApp notifications. Resolution per value: `.env` (config/whatsapp.php, empty = not set)
 * > admin option (`whatsapp.*` in bs_options) > default. The main switch and the budget are admin only.
 * Nothing is cached across calls, so an admin change takes effect at once. Option reads tolerate a missing
 * options table like ResolvesMailOptions.
 */
final class WhatsAppSettings
{
    /** Fields that have an .env override and a plain admin option. */
    private const PLAIN = [
        'driver', 'sandbox', 'sender', 'sandbox_sender', 'webhook_base_url',
        'api_base', 'api_messages_path', 'api_account_path', 'api_service_path', 'signature_header',
    ];

    private const SECRET = ['account_sid', 'auth_token', 'messaging_service_sid'];

    private const DRIVERS = ['log', 'live', 'fake'];

    /** @param  NotificationRegistry|null  $registry  defaults to the app's registry (resolved on first use) */
    public function __construct(
        private readonly SecretOptions $secrets,
        private ?NotificationRegistry $registry = null,
    ) {}

    /** Option that switches a notification type on ('1') or off ('0'); unset = default of its definition. */
    public static function typeEnabledOption(string $type): string
    {
        return "whatsapp.type.$type.enabled";
    }

    /** Option with the estimated price of one message of a type; unset = global estimate. */
    public static function typePriceOption(string $type): string
    {
        return "whatsapp.price.$type";
    }

    /**
     * Whether a notification type may be queued and sent: its option `whatsapp.type.<key>.enabled`, else the
     * legacy option of its definition (when set), else the definition's default. Unknown types are off.
     */
    public function typeEnabled(string $type): bool
    {
        $definition = $this->registry()->type($type);
        if ($definition === null) {
            return false;
        }

        $value = $this->optionOrNull(self::typeEnabledOption($type));
        if ($value === null && $definition->legacyEnabledOption !== null) {
            $value = $this->optionOrNull($definition->legacyEnabledOption);
        }

        return $value === null ? $definition->defaultEnabled : $value === '1';
    }

    private function registry(): NotificationRegistry
    {
        return $this->registry ??= app(NotificationRegistry::class);
    }

    public function enabled(): bool
    {
        return $this->option('whatsapp.enabled') === '1';
    }

    /** `log` (default), `live` or `fake`; unknown values count as `log`. */
    public function driver(): string
    {
        $driver = $this->value('driver');

        return in_array($driver, self::DRIVERS, true) ? $driver : 'log';
    }

    /**
     * The driver that really runs: `live` becomes `log` on a staging database (name ends in `_staging`)
     * unless sandbox mode or WHATSAPP_ALLOW_LIVE_ON_STAGING is set.
     */
    public function effectiveDriver(): string
    {
        $driver = $this->driver();
        if ($driver === 'live' && $this->onStagingDatabase() && ! $this->sandbox() && ! $this->allowLiveOnStaging()) {
            return 'log';
        }

        return $driver;
    }

    private function onStagingDatabase(): bool
    {
        return StagingDatabase::active();
    }

    public function accountSid(): ?string
    {
        return $this->value('account_sid');
    }

    public function authToken(): ?string
    {
        return $this->value('auth_token');
    }

    public function messagingServiceSid(): ?string
    {
        return $this->value('messaging_service_sid');
    }

    public function sender(): ?string
    {
        return $this->value('sender');
    }

    public function sandbox(): bool
    {
        return $this->value('sandbox') === '1';
    }

    public function sandboxSender(): ?string
    {
        return $this->value('sandbox_sender');
    }

    public function webhookBaseUrl(): ?string
    {
        return $this->value('webhook_base_url');
    }

    public function allowLiveOnStaging(): bool
    {
        return filter_var(config('whatsapp.allow_live_on_staging'), FILTER_VALIDATE_BOOLEAN);
    }

    public function apiBase(): ?string
    {
        return $this->value('api_base');
    }

    public function apiMessagesPath(): ?string
    {
        return $this->value('api_messages_path');
    }

    public function apiAccountPath(): ?string
    {
        return $this->value('api_account_path');
    }

    public function apiServicePath(): ?string
    {
        return $this->value('api_service_path');
    }

    public function signatureHeader(): ?string
    {
        return $this->value('signature_header');
    }

    /**
     * Resolved value of a field (plain fields from .env or option, secrets decrypted); null when not set.
     * `sandbox` defaults to '0' and `driver` to 'log'.
     */
    public function value(string $field): ?string
    {
        $env = $this->env($field);
        if ($env !== null) {
            return $env;
        }

        $admin = in_array($field, self::SECRET, true)
            ? $this->secrets->get('whatsapp.'.$field)
            : $this->optionOrNull('whatsapp.'.$field);
        if ($admin !== null) {
            return $admin;
        }

        return match ($field) {
            'driver' => 'log',
            'sandbox' => '0',
            default => null,
        };
    }

    /** @return 'env'|'admin'|'default' */
    public function sourceOf(string $field): string
    {
        if ($this->env($field) !== null) {
            return 'env';
        }

        $stored = in_array($field, self::SECRET, true)
            ? $this->secrets->isSet('whatsapp.'.$field)
            : $this->optionOrNull('whatsapp.'.$field) !== null;

        return $stored ? 'admin' : 'default';
    }

    /** Template ID for a notification type and locale; falls back to the default locale, null if none. */
    public function templateId(BackedEnum|string $type, string $locale): ?string
    {
        $type = $type instanceof BackedEnum ? (string) $type->value : $type;

        return $this->optionOrNull("whatsapp.template.$type.$locale")
            ?? $this->optionOrNull("whatsapp.template.$type.".$this->defaultLocale());
    }

    public function defaultLocale(): string
    {
        return $this->optionOrNull('whatsapp.default_locale') ?? 'de';
    }

    public function defaultCountry(): string
    {
        return $this->optionOrNull('whatsapp.default_country') ?? '43';
    }

    /** Monthly budget in millionths of the currency unit; 0 = send nothing. */
    public function budgetMonthlyMicros(): int
    {
        return $this->micros($this->optionOrNull('whatsapp.budget_monthly'));
    }

    /**
     * Estimated price of one message in millionths; 0 = not set. With a type: its own price option when set
     * to a positive amount, else the global estimate. Test types always use the global estimate.
     */
    public function priceEstimateMicros(?string $type = null): int
    {
        $global = $this->micros($this->optionOrNull('whatsapp.price_estimate'));
        if ($type === null || $this->registry()->type($type)?->testType !== false) {
            return $global;
        }

        $own = $this->micros($this->optionOrNull(self::typePriceOption($type)));

        return $own > 0 ? $own : $global;
    }

    public function dailyLimitPerUser(): int
    {
        return $this->positiveInt('whatsapp.daily_limit_per_user', 3);
    }

    public function maxPerRun(): int
    {
        return $this->positiveInt('whatsapp.max_per_run', 30);
    }

    public function budgetAlertEmail(): ?string
    {
        return $this->optionOrNull('whatsapp.budget_alert_email');
    }

    public function pausedReason(): ?string
    {
        return $this->optionOrNull('whatsapp.paused_reason');
    }

    public function pause(string $reason): void
    {
        $this->write('whatsapp.paused_reason', $reason);
        Log::info('whatsapp settings changed', ['key' => 'whatsapp.paused_reason']);
    }

    public function resume(): void
    {
        $this->write('whatsapp.paused_reason', null);
        Log::info('whatsapp settings changed', ['key' => 'whatsapp.paused_reason']);
    }

    /**
     * Why nothing can be sent right now: `disabled`, `budget_missing`, `price_missing`, `credentials_missing`,
     * `config_missing`; null when ready. Driver log and fake need neither credentials nor provider config.
     */
    public function readyToSend(): ?string
    {
        if (! $this->enabled()) {
            return 'disabled';
        }
        if ($this->budgetMonthlyMicros() <= 0) {
            return 'budget_missing';
        }
        if ($this->priceEstimateMicros() <= 0) {
            return 'price_missing';
        }
        if ($this->effectiveDriver() !== 'live') {
            return null;
        }

        // Sandbox mode sends only from the sandbox sender; otherwise the messaging service or the sender.
        $hasSender = $this->sandbox()
            ? $this->sandboxSender() !== null
            : ($this->messagingServiceSid() !== null || $this->sender() !== null);
        if ($this->accountSid() === null || $this->authToken() === null || ! $hasSender) {
            return 'credentials_missing';
        }

        foreach (['api_base', 'api_messages_path', 'api_account_path', 'api_service_path', 'signature_header'] as $field) {
            if ($this->value($field) === null) {
                return 'config_missing';
            }
        }

        return null;
    }

    private function env(string $field): ?string
    {
        if (! in_array($field, [...self::PLAIN, ...self::SECRET], true)) {
            return null;
        }

        $config = config('whatsapp.'.$field);
        $value = is_scalar($config) ? trim((string) $config) : '';

        return $value === '' ? null : $value;
    }

    private function option(string $key): string
    {
        return $this->optionOrNull($key) ?? '';
    }

    private function optionOrNull(string $key): ?string
    {
        try {
            $value = Option::getValue($key);
        } catch (QueryException) {
            return null;
        }
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    private function positiveInt(string $key, int $default): int
    {
        $value = (int) ($this->optionOrNull($key) ?? 0);

        return $value > 0 ? $value : $default;
    }

    private function micros(?string $amount): int
    {
        if ($amount === null) {
            return 0;
        }
        $amount = str_replace(',', '.', $amount);

        return is_numeric($amount) ? max(0, (int) round((float) $amount * 1_000_000)) : 0;
    }

    private function write(string $key, ?string $value): void
    {
        if ($value === null) {
            Option::where('key', $key)->delete();

            return;
        }

        $row = Option::where('key', $key)->first();
        if ($row === null) {
            Option::create(['key' => $key, 'value' => $value, 'locale' => null]);
        } else {
            $row->update(['value' => $value]);
        }
    }
}
