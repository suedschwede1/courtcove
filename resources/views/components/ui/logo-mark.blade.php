@props(['name', 'size' => 44])
@php
    $sizeClass = [44 => 'ds-logo--44', 36 => 'ds-logo--36'][(int) $size] ?? 'ds-logo--44';
    $path = trim((string) ($bookingLogoPath ?? ''));
    $hasLogo = $path !== '' && file_exists(public_path($path));
@endphp
@if($hasLogo)
<span {{ $attributes->class(['ds-logo', $sizeClass]) }} role="img" aria-label="{{ $name }}"><img src="{{ asset($path) }}" alt="" class="ds-logo__img"></span>
@else
<span {{ $attributes->class(['ds-logo', $sizeClass]) }} role="img" aria-label="{{ $name }}"><svg class="ds-logo__glyph" viewBox="-6 -6 36 36" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M5.5 5.8c3 2.4 3 10 0 12.4M18.5 5.8c-3 2.4-3 10 0 12.4"></path></svg></span>
@endif
