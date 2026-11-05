@props(['loggedIn' => false])

<ul {{ $attributes->merge(['class' => 'cal-legend']) }} aria-label="{{ __('booking.calendar.legend.label') }}">
    @foreach(\App\Support\Calendar\CellState::legend((bool) $loggedIn) as $state)
        <li class="cal-legend__item" data-state="{{ $state->value }}">
            <span class="cal-swatch cal-swatch--{{ $state->value }}" aria-hidden="true"></span>
            {{ __($state->legendKey()) }}
        </li>
    @endforeach
</ul>
