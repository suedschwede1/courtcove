@props(['href' => null, 'active' => false])
@if ($href !== null)
<a href="{{ $href }}" {{ $attributes->class(['ds-chip']) }}@if ($active) aria-current="true"@endif>{{ $slot }}</a>
@else
<button type="button" {{ $attributes->class(['ds-chip']) }} aria-pressed="{{ $active ? 'true' : 'false' }}">{{ $slot }}</button>
@endif
