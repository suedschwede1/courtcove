@props(['variant' => 'bar'])
@php $adminModeOn = request()->cookie('admin_mode') === '1'; @endphp
<a href="{{ route('admin-mode.set', ['state' => $adminModeOn ? 'off' : 'on']) }}"
   role="switch"
   aria-checked="{{ $adminModeOn ? 'true' : 'false' }}"
   aria-label="{{ $adminModeOn ? __('booking.nav.admin_mode_off') : __('booking.nav.admin_mode_on') }}"
   {{ $attributes->class(['ds-switch', $variant === 'menu' ? 'ds-switch--menu' : 'ds-switch--bar']) }}>
    <span class="ds-switch__label">{{ __('booking.nav.admin_mode') }}</span>
    <span class="ds-switch__track" aria-hidden="true"><span class="ds-switch__knob"></span></span>
</a>
