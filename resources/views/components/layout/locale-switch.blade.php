@props(['variant' => 'bar'])
<div {{ $attributes->class(['ds-locale', $variant === 'menu' ? 'ds-locale--menu' : 'ds-locale--bar']) }} role="group" aria-label="{{ __('booking.nav.language') }}">
    @foreach(config('app.available_locales') as $loc)
        @if(! $loop->first)
            <span class="ds-locale__sep" aria-hidden="true">|</span>
        @endif
        @if($loc === app()->getLocale())
            <span class="ds-locale__current" aria-current="true">{{ strtoupper($loc) }}</span>
        @else
            <a class="ds-locale__link" href="{{ route('lang.switch', ['locale' => $loc]) }}" hreflang="{{ $loc }}" lang="{{ $loc }}">{{ strtoupper($loc) }}</a>
        @endif
    @endforeach
</div>
