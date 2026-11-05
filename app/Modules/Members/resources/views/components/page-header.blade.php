@props(['title', 'eyebrow' => __('booking.admin.nav.eyebrow')])
@php
    $section = \App\Modules\Members\Support\MembersNavigation::current(request());
@endphp
<header {{ $attributes->class('mbr-page-header') }}>
    <div class="mbr-page-header__text">
        <p class="mbr-page-header__eyebrow">{{ $eyebrow }}</p>
        <h1 class="mbr-page-header__title">{{ $title }}</h1>
    </div>
    @if(isset($actions) && $actions->isNotEmpty())
        <div class="mbr-page-header__actions">{{ $actions }}</div>
    @endif
</header>
@if($section !== null)
    {{-- Sub-pages of the section that is active in the admin sidebar; phones scroll the active tab into view. --}}
    <x-ui.tabs class="relative" x-data x-init="const a = $el.querySelector('[aria-current]'); if (a && a.offsetLeft + a.offsetWidth > $el.clientWidth) { $el.scrollLeft = a.offsetLeft - 16 }" :label="$section['label']" :items="$section['items']" />
@endif
