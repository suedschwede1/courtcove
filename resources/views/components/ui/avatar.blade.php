@props(['name', 'href' => null, 'label' => null])
@php $initials = \App\Support\Ui\Initials::from($name); @endphp
@if($href)
<a href="{{ $href }}" {{ $attributes->class(['ds-avatar']) }} aria-label="{{ $label ?? $name }}">{{ $initials }}</a>
@else
<span {{ $attributes->class(['ds-avatar']) }} aria-hidden="true">{{ $initials }}</span>
@endif
