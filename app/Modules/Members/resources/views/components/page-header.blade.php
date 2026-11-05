{{--
    Head of every Members page: eyebrow, the page's only h1 and actions, then the module's tab row and, on the
    settings pages, the row of settings pages. Slot phoneAction: the primary action as a full-width button pinned
    to the bottom on phones (the matching header button carries mbr-phone-hidden).
--}}
@props(['title', 'eyebrow' => __('members::admin.area.eyebrow')])
@php
    $tabs = \App\Modules\Members\Support\MembersNavigation::tabs(request());
    $settings = \App\Modules\Members\Support\MembersNavigation::settings(request());
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
{{-- Phones scroll the active tab into view. --}}
<x-ui.tabs class="mbr-tabs" x-data x-init="const a = $el.querySelector('[aria-current]'); if (a && a.offsetLeft + a.offsetWidth > $el.clientWidth) { $el.scrollLeft = a.offsetLeft - 16 }" :label="__('members::admin.area.tabs')" :items="$tabs" />
@if($settings !== null)
    <nav class="mbr-subnav" x-data x-init="const a = $el.querySelector('[aria-current]'); if (a && a.offsetLeft + a.offsetWidth > $el.clientWidth) { $el.scrollLeft = a.offsetLeft - 16 }" aria-label="{{ __('members::admin.settings.title') }}">
        @foreach($settings as $item)
            <x-ui.chip :href="$item['url']" :active="$item['active']">{{ $item['label'] }}</x-ui.chip>
        @endforeach
    </nav>
@endif
@if(isset($phoneAction) && $phoneAction->isNotEmpty())
    @push('members-phone-action')
        <div class="ds-phone-actionbar-spacer" aria-hidden="true"></div>
        <div class="ds-phone-actionbar mbr-phone-action">{{ $phoneAction }}</div>
    @endpush
@endif
