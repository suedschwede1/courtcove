{{--
    Left sidebar of the admin shell. Without props it lists the administration groups (AdminNavigation); an area
    with its own navigation (Members) passes groups, eyebrow and label.
--}}
@props(['groups' => null, 'eyebrow' => null, 'label' => null])
@php
    $clubName = trim((string) \App\Models\Option::getValue('service.name', config('booking.name')));
    $clubName = $clubName !== '' ? $clubName : config('booking.name');
    $navGroups = $groups ?? (auth()->user() ? app(\App\Services\AdminNavigation::class)->groups(auth()->user(), request()) : []);
    $eyebrow ??= __('booking.admin.nav.eyebrow');
    $label ??= __('booking.admin.nav.label');
@endphp
<aside id="admin-nav" class="ds-admin-sidebar ds-on-chrome" x-bind:class="{ 'is-open': navOpen }" aria-label="{{ $label }}">
    <div class="ds-admin-sidebar__top">
        <a href="{{ route('calendar.index') }}" class="ds-admin-sidebar__brand">
            <x-ui.logo-mark :name="$clubName" :size="36" />
            <span class="ds-admin-sidebar__titles">
                <span class="ds-admin-sidebar__club">{{ $clubName }}</span>
                <span class="ds-admin-sidebar__eyebrow">{{ $eyebrow }}</span>
            </span>
        </a>
        <x-ui.icon-button icon="close" variant="chrome" class="ds-admin-sidebar__close" :label="__('booking.admin.nav.close')" x-ref="navClose" x-on:click="navOpen = false; $refs.navButton?.focus()" />
    </div>

    <a href="{{ route('calendar.index') }}" class="ds-admin-sidebar__back"><x-ui.icon name="chevron-left" :size="16" />{{ __('booking.admin.to_calendar') }}</a>

    <nav class="ds-admin-sidebar__nav" aria-label="{{ $label }}">
        @foreach($navGroups as $group)
            <div class="ds-admin-sidebar__section">
                <p class="ds-admin-sidebar__group" id="admin-nav-{{ $group->key }}">{{ $group->label }}</p>
                <ul class="ds-admin-sidebar__list" aria-labelledby="admin-nav-{{ $group->key }}">
                    @foreach($group->items as $item)
                        <li><a href="{{ $item->url }}" class="ds-admin-sidebar__link"@if($item->active) aria-current="page"@endif>{{ $item->label }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    @auth
        <div class="ds-admin-sidebar__foot">
            <span class="ds-admin-sidebar__alias">{{ auth()->user()->alias }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="ds-admin-sidebar__logout">{{ __('booking.nav.logout') }}</button>
            </form>
        </div>
    @endauth
</aside>
