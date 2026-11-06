@props(['variant' => 'bar'])
@php
    // Shown to accounts that hold a privilege as stored (raw); the state is the request's admin mode cookie.
    $adminModeOn = \App\Support\AdminMode::cookieOn();
    $formId = 'admin-mode-form-'.$variant;
@endphp
{{-- POST with CSRF; the button sits outside the (hidden) form via form="", so the switch keeps its place in the flex rows. --}}
<form id="{{ $formId }}" method="POST" action="{{ route('admin-mode.set', ['state' => $adminModeOn ? 'off' : 'on']) }}" hidden>
    @csrf
    <input type="hidden" name="redirect" value="{{ \App\Support\AdminMode::intendedPath(request()) }}">
</form>
<button type="submit" form="{{ $formId }}"
   role="switch"
   aria-checked="{{ $adminModeOn ? 'true' : 'false' }}"
   aria-label="{{ $adminModeOn ? __('booking.nav.admin_mode_off') : __('booking.nav.admin_mode_on') }}"
   {{ $attributes->class(['ds-switch', $variant === 'menu' ? 'ds-switch--menu' : 'ds-switch--bar']) }}>
    <span class="ds-switch__label">{{ __('booking.nav.admin_mode') }}</span>
    <span class="ds-switch__track" aria-hidden="true"><span class="ds-switch__knob"></span></span>
</button>
