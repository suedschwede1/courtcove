<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GeoLookup;
use App\Services\LoginVerificationService;
use App\Services\TrustedDeviceService;
use App\Services\TrustedDeviceSettings;
use App\Support\Audit\AdminConfigAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin page "Sicherheit": scope of the trusted-device sign-in (off, admins, admins + assistants, all).
 * A value from .env locks the page. Widening the scope is refused while the configuring admin would be
 * covered without a confirmed device of their own (that would lock them out at the next sign-in); the page then
 * offers to confirm this browser by e-mail code first.
 */
final class SecurityController extends Controller
{
    public function __construct(
        private readonly TrustedDeviceSettings $settings,
        private readonly TrustedDeviceService $devices,
    ) {}

    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('admin.security.edit', [
            'scope' => $this->settings->scope(),
            'scopes' => TrustedDeviceSettings::SCOPES,
            'locked' => $this->settings->lockedByEnv(),
            'days' => max(1, (int) config('booking.security.trusted_device_days', 30)),
            'hasDevice' => $user !== null && $this->devices->validDevice($request, $user) !== null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $back = redirect()->route('admin.security.edit');
        $admin = $request->user() ?? abort(403);

        if ($this->settings->lockedByEnv()) {
            return $back->withErrors(['scope' => __('booking.admin.security.locked_refused')]);
        }

        $data = $request->validate(['scope' => ['required', Rule::in(TrustedDeviceSettings::SCOPES)]], [], ['scope' => __('booking.admin.security.legend')]);
        $new = $data['scope'];
        $old = $this->settings->scope();

        if ($new === $old) {
            return $back->with('success', __('booking.admin.security.saved'));
        }

        // Lock-out guard: whoever is covered by the new level needs a confirmed device before saving.
        if ($this->settings->covers($admin, $new) && $this->devices->validDevice($request, $admin) === null) {
            return $back->withErrors(['scope' => __('booking.admin.security.needs_device')])->withInput()->with('needs_device', true);
        }

        $this->settings->store($new);
        AdminConfigAudit::record($admin, 'security', ['trusted_device_scope: '.$old.' > '.$new]);

        $back = $back->with('success', __('booking.admin.security.saved'));

        return TrustedDeviceSettings::rank($new) > TrustedDeviceSettings::rank($old)
            ? $back->with('info', __('booking.admin.security.widened_notice'))
            : $back;
    }

    /** Confirms the admin's own browser by e-mail code (also while the scope is off), then returns here. */
    public function confirmDevice(Request $request, LoginVerificationService $verification, GeoLookup $geo): RedirectResponse
    {
        $admin = $request->user() ?? abort(403);
        $ip = (string) $request->ip();

        $started = $verification->begin($request, $admin, false, route('admin.security.edit'), $ip, $geo->resolveCountry($ip), ['new_device'], true);

        if ($started === LoginVerificationService::CAP) {
            return redirect()->route('admin.security.edit')->withErrors([
                'scope' => __('booking.auth.verify.too_many_codes', ['minutes' => (int) ceil($verification->waitSeconds($admin, $ip) / 60)]),
            ]);
        }

        // The code step also trusts the browser although the scope may not require a device (yet).
        $pending = $request->session()->get('login.pending_verification');
        if (is_array($pending)) {
            $pending['force_trust'] = true;
            $request->session()->put('login.pending_verification', $pending);
        }

        return $started === LoginVerificationService::MAIL_FAILED
            ? redirect()->route('login.verify.show')->withErrors(['code' => __('booking.auth.verify.send_failed')])
            : redirect()->route('login.verify.show');
    }
}
