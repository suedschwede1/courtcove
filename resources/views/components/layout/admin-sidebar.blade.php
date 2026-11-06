{{--
    Left sidebar of the area shell (layouts.admin): the one sidebar of the administration, the Members area and the
    tournament area. `area` is the area's navigation provider (App\Support\Navigation\AreaNavigation); without it the
    administration's (AdminNavigation) is used. Sticky column from 48rem, slide-in panel below (navOpen of the shell).
    Guests (public tournament pages) see the entries visible to guests and a login button in the foot.
--}}
@props(['area' => null])
@php
    $clubName = trim((string) \App\Models\Option::getValue('service.name', config('booking.name')));
    $clubName = $clubName !== '' ? $clubName : config('booking.name');
    $area ??= app(\App\Services\AdminNavigation::class);
    $navGroups = $area->groups(auth()->user(), request());
    $eyebrow = $area->eyebrow();
    $label = $area->label();
@endphp
{{-- A long sidebar (tournaments) scrolls in its column: the current entry is brought into view on wide screens. --}}
<aside id="admin-nav" class="ds-admin-sidebar ds-on-chrome" x-bind:class="{ 'is-open': navOpen }" aria-label="{{ $label }}" x-init="if (window.innerWidth >= 768) { const a = $el.querySelector('[aria-current=page]'); if (a && a.getBoundingClientRect().bottom > window.innerHeight) $el.scrollTop += a.getBoundingClientRect().top - $el.clientHeight / 2 }">
    <div class="ds-admin-sidebar__top">
        <a href="{{ route('calendar.index') }}" class="ds-admin-sidebar__brand">
            <x-ui.logo-mark :name="$clubName" :size="36" />
            <span class="ds-admin-sidebar__titles">
                <span class="ds-admin-sidebar__club">{{ $clubName }}</span>
                <span class="ds-admin-sidebar__eyebrow">{{ $eyebrow }}</span>
            </span>
        </a>
        <x-ui.icon-button icon="close" variant="chrome" class="ds-admin-sidebar__close" :label="__('booking.admin.nav.close')" x-ref="navClose" x-on:click="navOpen = false; $nextTick(() => $refs.navButton?.focus())" />
    </div>

    <a href="{{ route('calendar.index') }}" class="ds-admin-sidebar__back"><x-ui.icon name="chevron-left" :size="16" />{{ __('booking.admin.to_calendar') }}</a>

    <nav class="ds-admin-sidebar__nav" aria-label="{{ $label }}">
        @foreach($navGroups as $group)
            <div class="ds-admin-sidebar__section">
                <p class="ds-admin-sidebar__group" id="admin-nav-{{ $group->key }}">{{ $group->label }}</p>
                <x-layout.area-nav-list :items="$group->items" aria-labelledby="admin-nav-{{ $group->key }}" />
            </div>
        @endforeach
    </nav>

    <div class="ds-admin-sidebar__foot">
        @auth
            <span class="ds-admin-sidebar__alias">{{ auth()->user()->alias }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="ds-admin-sidebar__logout">{{ __('booking.nav.logout') }}</button>
            </form>
        @else
            <x-ui.button :href="route('login', ['redirect_to' => url()->full()])" class="ds-admin-sidebar__login">{{ __('booking.nav.login') }}</x-ui.button>
        @endauth
    </div>
</aside>
