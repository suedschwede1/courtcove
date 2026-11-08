<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLogEntry;
use App\Models\Option;
use App\Notifications\AdminTestNotification;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Types\NotificationType;
use App\Services\Notifications\BudgetService;
use App\Services\Notifications\ConsentService;
use App\Services\Notifications\NotificationDispatcher;
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
 * Admin page "WhatsApp": access data, switches, budget fields and templates. Secrets are write-only (never
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
        'followup_closed' => 'whatsapp.followup_closed',
    ];

    private const LOCALES = ['de', 'en'];

    public function __construct(
        private readonly WhatsAppSettings $settings,
        private readonly SecretOptions $secrets,
        private readonly WhatsAppAdminStatus $status,
        private readonly BudgetService $budget,
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
        $gate = $consents->gate($admin, forAdminTest: true);
        if (! $gate->passed) {
            return $refuse((string) $gate->reason);
        }
        $consent = $consents->activeConsent($admin);
        if ($consent === null || ! $consents->matchesCurrentPhone($consent, $admin)) {
            return $refuse('no_consent');
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

        $templates = [];
        foreach ($this->templateFields() as $field => [$key]) {
            $templates[$field] = (string) Option::getValue($key, '');
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
            'templates' => $templates,
            'templateFields' => $this->templateFields(),
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
        foreach ($this->templateFields() as $field => [$key]) {
            if (array_key_exists($field, $data) && $this->store($key, $data[$field] ?? null)) {
                $changed[] = $field;
            }
        }

        foreach (['sandbox', 'followup_closed'] as $field) {
            if ($field === 'sandbox' && in_array($field, $locked, true)) {
                continue;
            }
            if ($this->store(self::SWITCHES[$field], $request->boolean($field) ? '1' : null)) {
                $changed[] = $field;
            }
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
            DB::beginTransaction();
            $this->store(self::SWITCHES['enabled'], '1');
            $reason = $this->settings->readyToSend();
            if ($reason === null) {
                DB::commit();
            } else {
                DB::rollBack();
                $this->store(self::SWITCHES['enabled'], null);
                $refusal = __('booking.admin.whatsapp.enable_refused', ['reason' => __('booking.admin.whatsapp.not_ready.'.$reason)]);
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
     * @return array<string, array{0: string, 1: string, 2: string}> field => [option key, type template key, locale]
     */
    private function templateFields(): array
    {
        $fields = [];
        foreach (NotificationType::cases() as $type) {
            foreach (self::LOCALES as $locale) {
                $fields['tpl_'.$type->templateKey().'_'.$locale] = ['whatsapp.template.'.$type->templateKey().'.'.$locale, $type->templateKey(), $locale];
            }
        }

        return $fields;
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
        foreach (array_keys($this->templateFields()) as $field) {
            $rules[$field] = ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'];
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
        foreach ($this->templateFields() as $field => [, $type, $locale]) {
            $names[$field] = __('booking.admin.whatsapp.template_id', [
                'type' => __('booking.admin.whatsapp.templates.'.$type),
                'locale' => strtoupper($locale),
            ]);
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
