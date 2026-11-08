<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLogEntry;
use App\Models\Option;
use App\Notifications\AdminTestNotification;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Registry\NotificationRegistry;
use App\Notifications\Registry\NotificationTypeDefinition;
use App\Notifications\Types\NotificationTypes;
use App\Services\Notifications\BudgetService;
use App\Services\Notifications\ConsentService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationStatistics;
use App\Services\Notifications\SecretOptions;
use App\Services\Notifications\Transport\TransportFactory;
use App\Services\Notifications\WhatsAppAdminStatus;
use App\Services\Notifications\WhatsAppSettings;
use App\Support\Audit\AdminConfigAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Admin page "WhatsApp": access data, switches, budget fields and one block per registered notification type
 * (switch, template IDs per locale, variables, optional price, figures). Secrets are write-only (never
 * rendered, never flashed), a value from .env locks its field, enabling needs WhatsAppSettings::readyToSend().
 */
final class WhatsAppController extends Controller
{
    /** Secret field => change-log text (German on purpose: the log is for the admins, and holds no values). */
    private const SECRETS = [
        'account_sid' => 'Kontokennung geändert',
        'auth_token' => 'Auth-Token geändert',
        'messaging_service_sid' => 'Dienstkennung geändert',
    ];

    /** Fields with an .env override and a plain admin option: field => option key. */
    private const ENV_FIELDS = [
        'driver' => 'whatsapp.driver',
        'sandbox' => 'whatsapp.sandbox',
        'sender' => 'whatsapp.sender',
        'sandbox_sender' => 'whatsapp.sandbox_sender',
    ];

    /** Admin-only text fields: field => option key. */
    private const TEXT_FIELDS = [
        'sender_label' => 'whatsapp.sender_label',
        'default_locale' => 'whatsapp.default_locale',
        'default_country' => 'whatsapp.default_country',
        'budget_monthly' => 'whatsapp.budget_monthly',
        'price_estimate' => 'whatsapp.price_estimate',
        'daily_limit_per_user' => 'whatsapp.daily_limit_per_user',
        'budget_alert_email' => 'whatsapp.budget_alert_email',
    ];

    /** Switches, stored as '1' or removed (the default is off). */
    private const SWITCHES = [
        'enabled' => 'whatsapp.enabled',
        'sandbox' => 'whatsapp.sandbox',
    ];

    private const LOCALES = ['de', 'en'];

    public function __construct(
        private readonly WhatsAppSettings $settings,
        private readonly SecretOptions $secrets,
        private readonly WhatsAppAdminStatus $status,
        private readonly BudgetService $budget,
        private readonly NotificationRegistry $registry,
        private readonly NotificationStatistics $statistics,
    ) {}

    /** Connection check: reads account and service, sends nothing, shows ok or an error code only. */
    public function probe(TransportFactory $transports): RedirectResponse
    {
        $back = redirect()->route('admin.whatsapp.edit');

        if ($this->settings->effectiveDriver() === 'log') {
            return $back->with('info', __('booking.admin.whatsapp.probe_log_driver'));
        }
        if ($this->settings->effectiveDriver() === 'live'
            && ($this->settings->accountSid() === null || $this->settings->authToken() === null)) {
            return $back->with('error', __('booking.admin.whatsapp.probe_no_credentials'));
        }

        $result = $transports->make()->probe();
        if ($result->ok) {
            return $back->with('success', __('booking.admin.whatsapp.probe_ok'));
        }

        // Only a short code is shown; nothing of the response is kept.
        $code = preg_match('/^[a-z0-9_]{1,24}$/', (string) $result->code) === 1 ? (string) $result->code : 'error';

        return $back->with('error', __('booking.admin.whatsapp.probe_failed', ['code' => $code]));
    }

    /** Test message to the admin's own number: needs the admin's active consent and the (admin-test) gate. */
    public function test(
        Request $request,
        ConsentService $consents,
        NotificationDispatcher $dispatcher,
        WhatsAppChannel $channel,
    ): RedirectResponse {
        $back = redirect()->route('admin.whatsapp.edit');
        $admin = $request->user();
        $refuse = fn (string $reason) => $back->with('error', __('booking.admin.whatsapp.test_refused.'.$reason));

        if ($admin === null) {
            abort(403);
        }
        // The consent the admin test needs is the purpose of its type definition (player search today).
        $purpose = $this->registry->type(NotificationTypes::ADMIN_TEST)?->purpose;
        $gate = $consents->gate($admin, true, $purpose);
        if (! $gate->passed) {
            return $refuse((string) $gate->reason);
        }
        $consent = $purpose === null ? null : $consents->activeConsent($admin, $purpose);
        if ($purpose !== null && ($consent === null || ! $consents->matchesCurrentPhone($consent, $admin))) {
            // The flash text cannot hold a link; the page shows one next to the test button.
            return $refuse('no_consent')->with('whatsapp_account_link', true);
        }
        if ($this->settings->pausedReason() !== null) {
            return $refuse('paused');
        }
        $notReady = $this->settings->readyToSend();
        if ($notReady !== null) {
            return $back->with('error', __('booking.admin.whatsapp.enable_refused', ['reason' => __('booking.admin.whatsapp.not_ready.'.$notReady)]));
        }

        // The posted number (if any) is never read: the target is always the admin's own stored number.
        $channel->send($admin, new AdminTestNotification);
        $report = $dispatcher->drain(1, 20);

        if ($report->sent > 0) {
            return $back->with('success', __('booking.admin.whatsapp.test_sent'));
        }
        if ($report->skipped !== []) {
            $code = (string) array_key_first($report->skipped);
            $key = 'booking.admin.whatsapp.skip_reasons.'.$code;
            $reason = trans($key) === $key ? $code : trans($key);

            return $back->with('error', __('booking.admin.whatsapp.test_skipped', ['reason' => $reason]));
        }
        if ($report->failed > 0 || $report->retried > 0) {
            return $back->with('error', __('booking.admin.whatsapp.test_failed'));
        }

        return $back->with('info', __('booking.admin.whatsapp.test_queued'));
    }

    public function resume(Request $request): RedirectResponse
    {
        $this->settings->resume();
        if ($request->user() !== null) {
            AdminConfigAudit::record($request->user(), 'whatsapp', ['resume']);
        }

        return redirect()->route('admin.whatsapp.edit')->with('success', __('booking.admin.whatsapp.resumed'));
    }

    /** @return array{month: string, messages: int, percent: int, used: string, budget: string, hasBudget: bool, previous: ?string} */
    private function budgetBlock(): array
    {
        $snap = $this->budget->current();
        $previous = $snap->previousMonth;

        return [
            'month' => Carbon::createFromFormat('!Y-m', $snap->month)->translatedFormat('F Y'),
            'messages' => $snap->messages,
            'percent' => $snap->percent,
            'used' => WhatsAppAdminStatus::money($snap->estimatedMicros),
            'budget' => WhatsAppAdminStatus::money($snap->budgetMicros),
            'hasBudget' => $snap->budgetMicros > 0,
            'previous' => $previous === null ? null : __('booking.admin.whatsapp.budget_previous', [
                'messages' => $previous['messages'],
                'amount' => WhatsAppAdminStatus::money($previous['estimatedMicros']),
            ]),
        ];
    }

    public function edit(): View
    {
        $envLocked = [];
        foreach (array_keys(self::ENV_FIELDS) as $field) {
            $envLocked[$field] = $this->settings->sourceOf($field) === 'env';
        }

        // A locked field shows the .env value (never a secret); the others show what is stored.
        $values = [];
        foreach ([...self::ENV_FIELDS, ...self::TEXT_FIELDS, ...self::SWITCHES] as $field => $key) {
            $values[$field] = ($envLocked[$field] ?? false)
                ? (string) $this->settings->value($field)
                : (string) Option::getValue($key, '');
        }

        $secretStates = [];
        foreach (array_keys(self::SECRETS) as $field) {
            $secretStates[$field] = $this->secretState($field);
        }

        $reason = $this->settings->readyToSend();
        $liveConfigMissing = $this->settings->effectiveDriver() === 'live' && in_array(null, [
            $this->settings->apiBase(), $this->settings->apiMessagesPath(), $this->settings->apiAccountPath(),
            $this->settings->apiServicePath(), $this->settings->signatureHeader(),
        ], true);

        return view('admin.whatsapp.edit', [
            'values' => $values,
            'types' => $this->typeBlocks(),
            'secretStates' => $secretStates,
            'envLocked' => $envLocked,
            'driver' => $this->settings->driver(),
            'effectiveDriver' => $this->settings->effectiveDriver(),
            'enabledNow' => $this->settings->enabled(),
            'notReady' => in_array($reason, ['budget_missing', 'price_missing', 'credentials_missing'], true) ? $reason : null,
            'configMissing' => $liveConfigMissing,
            'problemCount' => $this->problemCount(),
            'budget' => $this->budgetBlock(),
            'notices' => $this->status->notices(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $locked = array_filter(
            array_keys(self::ENV_FIELDS),
            fn (string $field): bool => $this->settings->sourceOf($field) === 'env',
        );
        $lockedSecrets = array_filter(
            array_keys(self::SECRETS),
            fn (string $field): bool => $this->settings->sourceOf($field) === 'env',
        );

        $rules = $this->rules();
        foreach ([...$locked, ...$lockedSecrets] as $field) {
            unset($rules[$field]);
        }

        $validator = Validator::make($request->all(), $rules, [], $this->attributeNames());
        if ($validator->fails()) {
            return redirect()->route('admin.whatsapp.edit')
                ->withErrors($validator)
                ->withInput($request->except(array_keys(self::SECRETS)));
        }
        $data = $validator->validated();

        $changed = [];

        foreach (self::ENV_FIELDS as $field => $key) {
            if (in_array($field, $locked, true) || $field === 'sandbox' || ! array_key_exists($field, $data)) {
                continue;
            }
            if ($this->store($key, $data[$field] ?? null)) {
                $changed[] = $field;
            }
        }
        foreach (self::TEXT_FIELDS as $field => $key) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field] ?? null;
            if (in_array($field, ['budget_monthly', 'price_estimate'], true) && $value !== null) {
                $value = str_replace(',', '.', (string) $value);
            }
            if ($this->store($key, $value)) {
                $changed[] = $field;
            }
        }
        $changed = [...$changed, ...$this->storeTypes($request, $data)];

        if (! in_array('sandbox', $locked, true) && $this->store(self::SWITCHES['sandbox'], $request->boolean('sandbox') ? '1' : null)) {
            $changed[] = 'sandbox';
        }

        foreach (self::SECRETS as $field => $logText) {
            $input = trim((string) ($data[$field] ?? ''));
            if (in_array($field, $lockedSecrets, true) || $input === '') {
                continue; // empty = unchanged
            }
            if ($this->secrets->get('whatsapp.'.$field) !== $input) {
                $this->secrets->set('whatsapp.'.$field, $input);
                $changed[] = $logText;
            }
        }

        // The main switch goes last: it is checked against the values saved above and falls back to off.
        $refusal = null;
        $wasEnabled = $this->settings->enabled();
        if ($request->boolean('enabled')) {
            // In a transaction: the dispatcher never sees the switch on unless the check passes.
            try {
                DB::transaction(function (): void {
                    $this->store(self::SWITCHES['enabled'], '1');
                    $reason = $this->settings->readyToSend();
                    if ($reason !== null) {
                        throw new \DomainException($reason); // rolls the switch back
                    }
                });
            } catch (\DomainException $e) {
                $this->store(self::SWITCHES['enabled'], null);
                $refusal = __('booking.admin.whatsapp.enable_refused', ['reason' => __('booking.admin.whatsapp.not_ready.'.$e->getMessage())]);
            }
        } else {
            $this->store(self::SWITCHES['enabled'], null);
        }
        if ($wasEnabled !== $this->settings->enabled()) {
            $changed[] = 'enabled';
        }

        if ($request->user() !== null) {
            AdminConfigAudit::record($request->user(), 'whatsapp', $changed);
        }

        $redirect = redirect()->route('admin.whatsapp.edit');

        return $refusal !== null
            ? $redirect->withErrors(['enabled' => $refusal])
            : $redirect->with('success', __('booking.admin.whatsapp.saved'));
    }

    /**
     * Form field names of a type, derived from its key: `type_<slug>_shown` (marker that the block was in the
     * form, so a partial update never flips a switch), `_enabled`, `_price` and `_tpl_<locale>`.
     *
     * @return array{shown: string, enabled: string, price: string, templates: array<string, string>}
     */
    private function typeFieldNames(NotificationTypeDefinition $type): array
    {
        $base = 'type_'.$type->slug();
        $templates = [];
        foreach (self::LOCALES as $locale) {
            $templates[$locale] = $base.'_tpl_'.$locale;
        }

        return ['shown' => $base.'_shown', 'enabled' => $base.'_enabled', 'price' => $base.'_price', 'templates' => $templates];
    }

    /**
     * Stores switch, price and template IDs of every registered type present in the request.
     *
     * @param  array<string, mixed>  $data  validated input
     * @return list<string> changed fields for the change log (names only)
     */
    private function storeTypes(Request $request, array $data): array
    {
        $changed = [];
        foreach ($this->registry->types() as $type) {
            $fields = $this->typeFieldNames($type);

            if ($request->has($fields['shown'])) {
                // Only the type option is written; an older option of the type is dropped (it is a read fallback).
                $before = $this->settings->typeEnabled($type->key);
                $this->store(WhatsAppSettings::typeEnabledOption($type->key), $request->boolean($fields['enabled']) ? '1' : '0');
                if ($type->legacyEnabledOption !== null) {
                    $this->store($type->legacyEnabledOption, null);
                }
                if ($before !== $this->settings->typeEnabled($type->key)) {
                    $changed[] = $fields['enabled'];
                }
            }

            if (! $type->testType && array_key_exists($fields['price'], $data)) {
                $price = $data[$fields['price']] ?? null;
                if ($this->store(WhatsAppSettings::typePriceOption($type->key), $price === null ? null : str_replace(',', '.', (string) $price))) {
                    $changed[] = $fields['price'];
                }
            }

            foreach ($fields['templates'] as $locale => $field) {
                if (array_key_exists($field, $data) && $this->store('whatsapp.template.'.$type->templateKey.'.'.$locale, $data[$field] ?? null)) {
                    $changed[] = $field;
                }
            }
        }

        return $changed;
    }

    /**
     * View data of the section "notification types": one block per registered type.
     *
     * @return list<array<string, mixed>>
     */
    private function typeBlocks(): array
    {
        $stats = $this->statistics->forTypes();
        $blocks = [];
        foreach ($this->registry->types() as $type) {
            $fields = $this->typeFieldNames($type);
            $enabled = $this->settings->typeEnabled($type->key);

            $templates = [];
            foreach ($fields['templates'] as $locale => $field) {
                $templates[$locale] = [
                    'field' => $field,
                    'value' => (string) Option::getValue('whatsapp.template.'.$type->templateKey.'.'.$locale, ''),
                    // Same lookup as the dispatcher (own locale, then the default locale).
                    'missing' => $this->settings->templateId($type->templateKey, $locale) === null,
                ];
            }

            $variables = $type->variablesKey === null ? [] : trans($type->variablesKey);
            $parent = $type->followupOf === null ? null : $this->registry->type($type->followupOf);

            $blocks[] = [
                'key' => $type->key,
                'slug' => $type->slug(),
                'label' => (string) __($type->labelKey),
                'description' => (string) __($type->descriptionKey),
                'followupOf' => $parent === null ? null : (string) __($parent->labelKey),
                'testType' => $type->testType,
                'fields' => $fields,
                'enabled' => $enabled,
                'price' => (string) Option::getValue(WhatsAppSettings::typePriceOption($type->key), ''),
                'templates' => $templates,
                'variables' => is_array($variables) ? array_values(array_map('strval', $variables)) : [],
                'stats' => $stats[$type->key],
            ];
        }

        return $blocks;
    }

    /** @return array<string, list<string>> */
    private function rules(): array
    {
        $rules = [
            // "fake" only stays valid while it is already the stored value.
            'driver' => ['sometimes', 'in:'.($this->settings->driver() === 'fake' ? 'log,live,fake' : 'log,live')],
            'sender' => ['nullable', 'string', 'max:40', 'regex:/^whatsapp:\+\d{6,15}$/'],
            'sandbox_sender' => ['nullable', 'string', 'max:40', 'regex:/^whatsapp:\+\d{6,15}$/'],
            'sender_label' => ['nullable', 'string', 'max:60'],
            'default_locale' => ['nullable', 'in:'.implode(',', self::LOCALES)],
            'default_country' => ['nullable', 'regex:/^\d{1,4}$/'],
            'budget_monthly' => ['nullable', 'regex:/^\d{1,7}([.,]\d{1,2})?$/'],
            'price_estimate' => ['nullable', 'regex:/^\d{1,4}([.,]\d{1,6})?$/'],
            'daily_limit_per_user' => ['nullable', 'integer', 'min:1', 'max:100'],
            'budget_alert_email' => ['nullable', 'email', 'max:128'],
        ];
        foreach (array_keys(self::SECRETS) as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255', 'not_regex:/\s/'];
        }
        foreach ($this->registry->types() as $type) {
            $fields = $this->typeFieldNames($type);
            foreach ($fields['templates'] as $field) {
                $rules[$field] = ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'];
            }
            $rules[$fields['price']] = $rules['price_estimate'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function attributeNames(): array
    {
        $names = [];
        foreach (['driver', 'sender', 'sandbox_sender', 'sender_label', 'default_locale', 'default_country', 'budget_monthly', 'price_estimate', 'daily_limit_per_user', 'budget_alert_email', 'account_sid', 'auth_token', 'messaging_service_sid'] as $field) {
            $names[$field] = __('booking.admin.whatsapp.'.$field);
        }
        foreach ($this->registry->types() as $type) {
            $fields = $this->typeFieldNames($type);
            foreach ($fields['templates'] as $locale => $field) {
                $names[$field] = __('booking.admin.whatsapp.template_id', ['type' => __($type->labelKey), 'locale' => strtoupper($locale)]);
            }
            $names[$fields['price']] = __('booking.admin.whatsapp.type_price_of', ['type' => __($type->labelKey)]);
        }

        return $names;
    }

    /** @return array{state: 'env'|'set'|'unreadable'|'unset', mask: string} */
    private function secretState(string $field): array
    {
        $key = 'whatsapp.'.$field;
        $state = match (true) {
            $this->settings->sourceOf($field) === 'env' => 'env',
            $this->secrets->isUnreadable($key) => 'unreadable',
            $this->secrets->isSet($key) => 'set',
            default => 'unset',
        };

        // The token is never shown, not even masked.
        $mask = $field !== 'auth_token' && in_array($state, ['env', 'set'], true)
            ? SecretOptions::mask($this->settings->value($field))
            : '';

        return ['state' => $state, 'mask' => $mask];
    }

    /** Writes an option (null/empty removes it); true when the stored value changed. */
    private function store(string $key, ?string $value): bool
    {
        $value = $value === null ? null : trim($value);
        $value = $value === '' ? null : $value;
        $row = Option::where('key', $key)->whereNull('locale')->first();
        $current = $row?->value;

        if ($value === $current) {
            return false;
        }

        if ($value === null) {
            Option::where('key', $key)->whereNull('locale')->delete();
        } elseif ($row === null) {
            Option::create(['key' => $key, 'value' => $value, 'locale' => null]);
        } else {
            $row->update(['value' => $value]);
        }

        return true;
    }

    /** Failed sends and sends skipped for a missing template in the last 7 days. */
    private function problemCount(): int
    {
        return NotificationLogEntry::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->where(function ($query): void {
                $query->where('status', NotificationLogEntry::FAILED)
                    ->orWhere(function ($skipped): void {
                        $skipped->where('status', NotificationLogEntry::SKIPPED)
                            ->where('skip_reason', NotificationLogEntry::TEMPLATE_MISSING);
                    });
            })
            ->count();
    }
}
