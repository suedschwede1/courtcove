@props(['variant' => 'bar'])
@php
    // For logged-in members: the state is the stored opt-in; a tap posts the opposite state (same service as My account).
    $exchangeOn = app(\App\Services\PlayerExchangeService::class)->isOptedIn(auth()->user());
    $formId = 'player-exchange-form-'.$variant;
    $hintId = 'player-exchange-hint-'.$variant;
@endphp
{{-- POST with CSRF; the button sits outside the (hidden) form via form="", like the admin mode switch. --}}
<form id="{{ $formId }}" method="POST" action="{{ route('player-exchange.set', ['state' => $exchangeOn ? 'off' : 'on']) }}" hidden>
    @csrf
    <input type="hidden" name="redirect" value="{{ \App\Support\AdminMode::intendedPath(request()) }}">
</form>
<button type="submit" form="{{ $formId }}"
   role="switch"
   aria-checked="{{ $exchangeOn ? 'true' : 'false' }}"
   aria-label="{{ $exchangeOn ? __('booking.nav.player_exchange_off') : __('booking.nav.player_exchange_on') }}"
   @if($variant === 'menu') aria-describedby="{{ $hintId }}" @else title="{{ __('booking.nav.player_exchange_hint') }}" @endif
   {{ $attributes->class(['ds-switch', $variant === 'menu' ? 'ds-switch--menu' : 'ds-switch--bar']) }}>
    <span class="ds-switch__label">
        {{ __('booking.nav.player_exchange') }}
        @if($variant === 'menu')
            <span class="ds-switch__hint" id="{{ $hintId }}">{{ __('booking.nav.player_exchange_hint') }}</span>
        @endif
    </span>
    <span class="ds-switch__track" aria-hidden="true"><span class="ds-switch__knob"></span></span>
</button>
