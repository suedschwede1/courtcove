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
    /** form field => option key */
    private const MAP = [
        'system_name' => 'service.name',
        'client_name_full' => 'client.name.full',
        'contact_email' => 'client.contact.email',
        'calendar_days' => 'service.calendar.days',
        'registration' => 'service.user.registration',
        'maintenance' => 'service.maintenance',
    ];

    public function edit(): View
    {
        $values = [];
        foreach (self::MAP as $field => $key) {
            $values[$field] = Option::getValue($key, '');
        }

        return view('admin.config.edit', compact('values'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'system_name' => ['nullable', 'string', 'max:255'],
            'client_name_full' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:128'],
            'calendar_days' => ['nullable', 'integer', 'min:1', 'max:31'],
            'registration' => ['nullable', 'in:0,1'],
            'maintenance' => ['nullable', 'in:0,1'],
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
