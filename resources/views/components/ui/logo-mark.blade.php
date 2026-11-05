@props(['name', 'size' => 44])
@php
    $sizeClass = [44 => 'ds-logo--44', 36 => 'ds-logo--36'][(int) $size] ?? 'ds-logo--44';
    $path = trim((string) ($bookingLogoPath ?? ''));
    $hasLogo = $path !== '' && file_exists(public_path($path));
@endphp
@if($hasLogo)
<span {{ $attributes->class(['ds-logo', $sizeClass]) }} role="img" aria-label="{{ $name }}"><img src="{{ asset($path) }}" alt="" class="ds-logo__img"></span>
@else
<span {{ $attributes->class(['ds-logo', $sizeClass]) }} role="img" aria-label="{{ $name }}"><span class="ds-logo__initials" aria-hidden="true">{{ \App\Support\Ui\Initials::from($name) }}</span></span>
@endif
