<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Option;
use App\Modules\TournamentBase\Support\MailSender;
use App\Services\BookingRules;
use App\Support\Mail\SystemMailSender;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

final class OptionController extends Controller
{
    private const GENERAL_MAP = [
        'client_name_full' => 'client.name.full',
        'client_name_short' => 'client.name.short',
        'contact_email' => 'client.contact.email',
        'client_email_cc' => 'client.contact.email.user-notifications',
        'client_phone' => 'client.contact.phone',
        'client_website' => 'client.website',
        'client_website_contact' => 'client.website.contact',
        'client_website_imprint' => 'client.website.imprint',
        'client_website_privacy' => 'client.website.privacy',
        'logo_path' => 'service.brand.logo_path',
        'system_name' => 'service.name',
        'service_name_short' => 'service.name.short',
        'service_description' => 'service.meta.description',
        'subject_type' => 'subject.type',
        'subject_square_type' => 'subject.square.type',
        'subject_square_plural' => 'subject.square.type.plural',
        'subject_unit' => 'subject.square.unit',
        'subject_unit_plural' => 'subject.square.unit.plural',
        'registration_heading' => 'service.user.registration.heading',
        'registration_welcome' => 'service.user.registration.welcome',
        'registration_intro' => 'service.user.registration.intro',
        'registration_email_help' => 'service.user.registration.email_help',
        'registration_privacy' => 'service.user.registration.privacy',
        'registration_success' => 'service.user.registration.success',
    ];

    /**
     * Sender of the tournament mails (read by TournamentBase MailSender). Unlike the fields
     * above, an emptied field removes its option, so the .env value or default applies again.
     */
    private const TOURNAMENT_MAIL_MAP = [
        'tournament_mail_from_address' => MailSender::OPTION_FROM_ADDRESS,
        'tournament_mail_from_name' => MailSender::OPTION_FROM_NAME,
        'tournament_mail_reply_to_address' => MailSender::OPTION_REPLY_TO_ADDRESS,
        'tournament_mail_reply_to_name' => MailSender::OPTION_REPLY_TO_NAME,
        'tournament_mail_signature_club' => MailSender::OPTION_SIGNATURE_CLUB,
        'tournament_mail_signature_url' => MailSender::OPTION_SIGNATURE_URL,
        'tournament_mail_signature_text' => MailSender::OPTION_SIGNATURE_TEXT,
    ];

    /**
     * Reply-To of the booking and account mails (read by SystemMailSender); clearable
     * like the tournament mail fields.
     */
    private const SYSTEM_MAIL_MAP = [
        'system_mail_booking_reply_to' => SystemMailSender::OPTION_BOOKING_REPLY_TO_ADDRESS,
        'system_mail_account_reply_to' => SystemMailSender::OPTION_ACCOUNT_REPLY_TO_ADDRESS,
    ];

    /** HH:MM, 00:00 to 23:59. */
    private const TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';

    /** Global "Zeitblock min./max. buchbar" (BookingRules): form in minutes, option in seconds, clearable. */
    private const TIME_BLOCK_MAP = [
        'time_block_bookable_min' => BookingRules::TIME_BLOCK_MIN,
        'time_block_bookable_max' => BookingRules::TIME_BLOCK_MAX,
    ];

    private const BEHAVIOR_MAP = [
        'registration' => 'service.user.registration',
        'activation' => 'service.user.activation',
        'maintenance' => 'service.maintenance',
        'quotes_enabled' => 'service.quotes.enabled',
        'calendar_hide' => 'service.calendar.day-exceptions',
        'max_active_bookings' => 'booking.max_active_bookings',
        'peak_limit_enabled' => 'peak_limit.enabled',
        'peak_limit_max' => 'peak_limit.max',
        'peak_limit_w1_start' => 'peak_limit.window_1_start',
        'peak_limit_w1_end' => 'peak_limit.window_1_end',
        'peak_limit_w2_start' => 'peak_limit.window_2_start',
        'peak_limit_w2_end' => 'peak_limit.window_2_end',
    ];

    public function edit(): View
    {
        return view('admin.config.edit', [
            'values' => $this->loadValues(self::GENERAL_MAP) + $this->loadValues(self::TOURNAMENT_MAIL_MAP) + $this->loadValues(self::SYSTEM_MAIL_MAP) + [
                'registration' => '',
                'activation' => '',
                'maintenance' => '',
                'peak_limit_enabled' => '',
                'peak_limit_w1_start' => '',
                'peak_limit_w1_end' => '',
                'peak_limit_w2_start' => '',
                'peak_limit_w2_end' => '',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(
            $this->generalRules() + $this->tournamentMailRules() + $this->systemMailRules(),
            [],
            $this->mailAttributeNames(),
        );
        $this->persist(self::GENERAL_MAP, $data);
        $this->persistClearable(self::TOURNAMENT_MAIL_MAP, $data);
        $this->persistClearable(self::SYSTEM_MAIL_MAP, $data);
        $this->flushCaches();

        return redirect()->route('admin.config.edit')->with('success', __('booking.messages.config_saved'));
    }

    public function editBehavior(): View
    {
        $values = $this->loadValues(self::BEHAVIOR_MAP, true);

        if ($values['quotes_enabled'] === '') {
            $values['quotes_enabled'] = '1';
        }

        if ($values['max_active_bookings'] === '') {
            $values['max_active_bookings'] = '0';
        }

        // Stored in seconds like the former court columns, shown in minutes; empty = not set (court values apply).
        foreach (self::TIME_BLOCK_MAP as $field => $key) {
            $seconds = app(BookingRules::class)->globalValue($key);
            $values[$field] = $seconds === null ? '' : (string) intdiv($seconds, 60);
        }

        return view('admin.config.behavior', [
            'values' => $values,
        ]);
    }

    public function updateBehavior(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), $this->behaviorRules());
        $validator->after(fn (ValidatorContract $v) => $this->validatePeakWindows($v, $request));
        $validator->after(fn (ValidatorContract $v) => $this->validateTimeBlocks($v, $request));
        $data = $validator->validate();

        $timeBlocks = [];
        foreach (array_keys(self::TIME_BLOCK_MAP) as $field) {
            if (array_key_exists($field, $data)) {
                $timeBlocks[$field] = $data[$field] === null ? null : (string) ((int) $data[$field] * 60);
            }
        }
        $this->persistClearable(self::TIME_BLOCK_MAP, $timeBlocks);

        // An unticked checkbox sends nothing: without this the global peak switch could never be turned off.
        $data['peak_limit_enabled'] = $request->boolean('peak_limit_enabled') ? '1' : '0';
        $this->persist(self::BEHAVIOR_MAP, $data);

        return redirect()->route('admin.config.behavior.edit')->with('success', __('booking.messages.config_saved'));
    }

    private function loadValues(array $map, bool $withPeakDefaults = false): array
    {
        $values = [];
        foreach ($map as $field => $key) {
            $values[$field] = Option::getValue($key, '');
        }

        if ($withPeakDefaults) {
            foreach ($this->peakDefaults() as $field => $default) {
                if (($values[$field] ?? '') === '') {
                    $values[$field] = $default;
                }
            }
        }

        return $values;
    }

    private function persist(array $map, array $data): void
    {
        foreach ($map as $field => $key) {
            if (! array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }

            $row = Option::where('key', $key)->whereNull('locale')->first();
            $row ? $row->update(['value' => (string) $data[$field]])
                 : Option::create(['key' => $key, 'value' => (string) $data[$field], 'locale' => null]);
        }
    }

    /**
     * Like persist(), but a submitted empty field deletes the option instead of being skipped.
     */
    private function persistClearable(array $map, array $data): void
    {
        foreach ($map as $field => $key) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = trim((string) $data[$field]);

            if ($value === '') {
                Option::where('key', $key)->whereNull('locale')->delete();

                continue;
            }

            Option::updateOrCreate(['key' => $key, 'locale' => null], ['value' => $value]);
        }
    }

    private function flushCaches(): void
    {
        Cache::forget('booking.service_name');
        Cache::forget('booking.logo_path');
    }

    private function generalRules(): array
    {
        return [
            'client_name_full' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:128'],
            'client_name_short' => ['nullable', 'string', 'max:64'],
            'client_email_cc' => ['nullable', 'in:0,1'],
            'client_phone' => ['nullable', 'string', 'max:64'],
            'client_website' => ['nullable', 'url', 'max:255'],
            'client_website_contact' => ['nullable', 'url', 'max:255'],
            'client_website_imprint' => ['nullable', 'url', 'max:255'],
            'client_website_privacy' => ['nullable', 'string', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'system_name' => ['nullable', 'string', 'max:255'],
            'service_name_short' => ['nullable', 'string', 'max:64'],
            'service_description' => ['nullable', 'string', 'max:512'],
            'subject_type' => ['nullable', 'string', 'max:64'],
            'subject_square_type' => ['nullable', 'string', 'max:64'],
            'subject_square_plural' => ['nullable', 'string', 'max:64'],
            'subject_unit' => ['nullable', 'string', 'max:64'],
            'subject_unit_plural' => ['nullable', 'string', 'max:64'],
            'registration_heading' => ['nullable', 'string', 'max:255'],
            'registration_welcome' => ['nullable', 'string', 'max:255'],
            'registration_intro' => ['nullable', 'string', 'max:2000'],
            'registration_email_help' => ['nullable', 'string', 'max:255'],
            'registration_privacy' => ['nullable', 'string', 'max:1000'],
            'registration_success' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Values end up in mail headers: names and the club line must stay on one line.
     * Addresses are capped at 254 characters (RFC 5321); bs_options.value is TEXT.
     */
    private function tournamentMailRules(): array
    {
        $singleLine = ['nullable', 'string', 'max:255', 'not_regex:/[\r\n]/'];

        return [
            'tournament_mail_from_address' => ['nullable', 'string', 'email', 'max:254'],
            'tournament_mail_from_name' => $singleLine,
            'tournament_mail_reply_to_address' => ['nullable', 'string', 'email', 'max:254'],
            'tournament_mail_reply_to_name' => $singleLine,
            'tournament_mail_signature_club' => $singleLine,
            'tournament_mail_signature_url' => ['nullable', 'string', 'url', 'max:255'],
            'tournament_mail_signature_text' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Same address rule as the tournament Reply-To (RFC 5321 length, one address per field).
     */
    private function systemMailRules(): array
    {
        return [
            'system_mail_booking_reply_to' => ['nullable', 'string', 'email', 'max:254'],
            'system_mail_account_reply_to' => ['nullable', 'string', 'email', 'max:254'],
        ];
    }

    /**
     * The field labels of the mail cards, so the error summary names the field instead of
     * the raw input name ("system mail booking reply to").
     *
     * @return array<string, string>
     */
    private function mailAttributeNames(): array
    {
        $names = [];

        foreach (array_keys(self::TOURNAMENT_MAIL_MAP + self::SYSTEM_MAIL_MAP) as $field) {
            $names[$field] = (string) __('booking.admin.cfg.'.$field);
        }

        return $names;
    }

    private function behaviorRules(): array
    {
        return [
            'registration' => ['nullable', 'in:0,1'],
            'activation' => ['nullable', 'in:immediate,manual,manual-email,email'],
            'maintenance' => ['nullable', 'in:0,1'],
            'quotes_enabled' => ['nullable', 'in:0,1'],
            'calendar_hide' => ['nullable', 'string', 'max:4096'],
            'max_active_bookings' => ['nullable', 'integer', 'min:0', 'max:999'],
            'peak_limit_enabled' => ['nullable', 'in:0,1'],
            'peak_limit_max' => ['nullable', 'integer', 'min:0', 'max:999'],
            'time_block_bookable_min' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'time_block_bookable_max' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'peak_limit_w1_start' => ['nullable', 'string', 'regex:'.self::TIME_PATTERN],
            'peak_limit_w1_end' => ['nullable', 'string', 'regex:'.self::TIME_PATTERN],
            'peak_limit_w2_start' => ['nullable', 'string', 'regex:'.self::TIME_PATTERN],
            'peak_limit_w2_end' => ['nullable', 'string', 'regex:'.self::TIME_PATTERN],
        ];
    }

    /** The daily maximum is 0 (unlimited) or at least the minimum duration (both in minutes). */
    private function validateTimeBlocks(ValidatorContract $validator, Request $request): void
    {
        if (array_intersect(array_keys(self::TIME_BLOCK_MAP), $validator->errors()->keys()) !== []) {
            return;
        }

        $min = $request->input('time_block_bookable_min');
        $max = $request->input('time_block_bookable_max');
        if (is_numeric($min) && is_numeric($max) && (int) $max > 0 && (int) $max < (int) $min) {
            $validator->errors()->add('time_block_bookable_max', __('booking.admin.time_blocks.max_below_min'));
        }
    }

    /**
     * Window 1 and window 2 must each start before they end, and window 2 must start at or after the end of
     * window 1 (no overlap). Empty fields count with the value the page shows (stored or default).
     */
    private function validatePeakWindows(ValidatorContract $validator, Request $request): void
    {
        $fields = ['peak_limit_w1_start', 'peak_limit_w1_end', 'peak_limit_w2_start', 'peak_limit_w2_end'];
        if (array_intersect($fields, $validator->errors()->keys()) !== []) {
            return;
        }

        $current = $this->loadValues(self::BEHAVIOR_MAP, true);
        $times = [];
        foreach ($fields as $field) {
            $value = $request->input($field);
            $times[$field] = is_string($value) && $value !== '' ? $value : (string) $current[$field];
        }

        if ($times['peak_limit_w1_start'] >= $times['peak_limit_w1_end']) {
            $validator->errors()->add('peak_limit_w1_end', __('booking.admin.peak_limit.window_order'));
        }
        if ($times['peak_limit_w2_start'] >= $times['peak_limit_w2_end']) {
            $validator->errors()->add('peak_limit_w2_end', __('booking.admin.peak_limit.window_order'));
        }
        if ($times['peak_limit_w2_start'] < $times['peak_limit_w1_end']) {
            $validator->errors()->add('peak_limit_w2_start', __('booking.admin.peak_limit.windows_overlap'));
        }
    }

    private function peakDefaults(): array
    {
        return [
            'peak_limit_w1_start' => config('booking.peak_limit.window_1_start', '08:00'),
            'peak_limit_w1_end' => config('booking.peak_limit.window_1_end', '12:00'),
            'peak_limit_w2_start' => config('booking.peak_limit.window_2_start', '17:00'),
            'peak_limit_w2_end' => config('booking.peak_limit.window_2_end', '21:00'),
        ];
    }
}
