<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Option;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

final class OptionController extends Controller
{
    private const MAP = [
        'client_name_full'       => 'client.name.full',
        'client_name_short'      => 'client.name.short',
        'contact_email'          => 'client.contact.email',
        'client_email_cc'        => 'client.contact.email.user-notifications',
        'client_phone'           => 'client.contact.phone',
        'client_website'         => 'client.website',
        'client_website_contact' => 'client.website.contact',
        'client_website_imprint' => 'client.website.imprint',
        'client_website_privacy' => 'client.website.privacy',
        'system_name'            => 'service.name',
        'service_name_short'     => 'service.name.short',
        'service_description'    => 'service.meta.description',
        'subject_type'           => 'subject.type',
        'subject_square_type'    => 'subject.square.type',
        'subject_square_plural'  => 'subject.square.type.plural',
        'subject_unit'           => 'subject.square.unit',
        'subject_unit_plural'    => 'subject.square.unit.plural',
        'calendar_days'          => 'service.calendar.days',
        'calendar_hide'          => 'service.calendar.day-exceptions',
        'registration'           => 'service.user.registration',
        'activation'             => 'service.user.activation',
        'maintenance'            => 'service.maintenance',
        'registration_heading'   => 'service.user.registration.heading',
        'registration_welcome'   => 'service.user.registration.welcome',
        'registration_intro'     => 'service.user.registration.intro',
        'registration_privacy'   => 'service.user.registration.privacy',
        'registration_success'   => 'service.user.registration.success',
        'peak_limit_enabled'     => 'peak_limit.enabled',
        'peak_limit_w1_start'    => 'peak_limit.window_1_start',
        'peak_limit_w1_end'      => 'peak_limit.window_1_end',
        'peak_limit_w2_start'    => 'peak_limit.window_2_start',
        'peak_limit_w2_end'      => 'peak_limit.window_2_end',
    ];

    public function edit(): View
    {
        $values = [];
        foreach (self::MAP as $field => $key) {
            $values[$field] = Option::getValue($key, '');
        }

        $peakDefaults = [
            'peak_limit_w1_start' => config('booking.peak_limit.window_1_start', '08:00'),
            'peak_limit_w1_end'   => config('booking.peak_limit.window_1_end', '12:00'),
            'peak_limit_w2_start' => config('booking.peak_limit.window_2_start', '17:00'),
            'peak_limit_w2_end'   => config('booking.peak_limit.window_2_end', '21:00'),
        ];
        foreach ($peakDefaults as $field => $default) {
            if ($values[$field] === '') {
                $values[$field] = $default;
            }
        }

        return view('admin.config.edit', compact('values'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'system_name' => ['nullable', 'string', 'max:255'],
            'client_name_full' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:128'],
            'client_name_short'      => ['nullable', 'string', 'max:64'],
            'client_email_cc'        => ['nullable', 'in:0,1'],
            'client_phone'           => ['nullable', 'string', 'max:64'],
            'client_website'         => ['nullable', 'url', 'max:255'],
            'client_website_contact' => ['nullable', 'url', 'max:255'],
            'client_website_imprint' => ['nullable', 'url', 'max:255'],
            'client_website_privacy' => ['nullable', 'url', 'max:255'],
            'service_name_short'     => ['nullable', 'string', 'max:64'],
            'service_description'    => ['nullable', 'string', 'max:512'],
            'subject_type'           => ['nullable', 'string', 'max:64'],
            'subject_square_type'    => ['nullable', 'string', 'max:64'],
            'subject_square_plural'  => ['nullable', 'string', 'max:64'],
            'subject_unit'           => ['nullable', 'string', 'max:64'],
            'subject_unit_plural'    => ['nullable', 'string', 'max:64'],
            'calendar_days'          => ['nullable', 'integer', 'min:1', 'max:31'],
            'calendar_hide'          => ['nullable', 'string', 'max:4096'],
            'registration'           => ['nullable', 'in:0,1'],
            'activation'             => ['nullable', 'in:immediate,manual,manual-email,email'],
            'maintenance'            => ['nullable', 'in:0,1'],
            'registration_heading'   => ['nullable', 'string', 'max:255'],
            'registration_welcome'   => ['nullable', 'string', 'max:255'],
            'registration_intro'     => ['nullable', 'string', 'max:2000'],
            'registration_privacy'   => ['nullable', 'string', 'max:1000'],
            'registration_success'   => ['nullable', 'string', 'max:1000'],
            'peak_limit_enabled'     => ['nullable', 'in:0,1'],
            'peak_limit_w1_start'    => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'peak_limit_w1_end'      => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'peak_limit_w2_start'    => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'peak_limit_w2_end'      => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        foreach (self::MAP as $field => $key) {
            if (! array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }

            $row = Option::where('key', $key)->whereNull('locale')->first();
            $row ? $row->update(['value' => (string) $data[$field]])
                 : Option::create(['key' => $key, 'value' => (string) $data[$field], 'locale' => null]);
        }

        Cache::forget('booking.service_name');

        return redirect()->route('admin.config.edit')->with('success', __('booking.messages.config_saved'));
    }
}

