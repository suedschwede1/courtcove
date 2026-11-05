@php
    $clubName = trim((string) \App\Models\Option::getValue('service.name', config('booking.name')));
    $clubName = $clubName !== '' ? $clubName : (string) config('booking.name');
    $groups = \App\Modules\Members\Support\MembersNavigation::groups(request());
@endphp
<aside id="members-sidebar" class="mbr-sidebar ds-on-chrome">
    <div class="mbr-sidebar__brand">
        <x-ui.logo-mark :name="$clubName" :size="36" />
        <span class="mbr-sidebar__brand-text">
            <span class="mbr-sidebar__club">{{ $clubName }}</span>
            <span class="mbr-sidebar__eyebrow">{{ __('booking.nav.members') }}</span>
        </span>
        <x-ui.icon-button icon="close" variant="chrome" :label="__('booking.nav.close_menu')" class="mbr-sidebar__close" x-ref="navClose" x-on:click="navOpen = false; $refs.menuButton.focus()" />
    </div>

    <a href="{{ route('calendar.index') }}" class="mbr-sidebar__back"><x-ui.icon name="chevron-left" :size="16" /><span>{{ __('booking.nav.back_to_calendar') }}</span></a>

    <nav aria-label="{{ __('booking.nav.members') }}" class="mbr-sidebar__nav">
        @foreach($groups as $group)
            <div class="mbr-sidebar__group" role="group" aria-labelledby="members-nav-group-{{ $loop->index }}">
                <span id="members-nav-group-{{ $loop->index }}" class="mbr-sidebar__label">{{ $group['label'] }}</span>
                @foreach($group['items'] as $item)
                    <a href="{{ $item['url'] }}" class="mbr-sidebar__link"@if($item['active']) aria-current="page"@endif>{{ $item['label'] }}</a>
                @endforeach
            </div>
        @endforeach
    </nav>

    @auth
        <div class="mbr-sidebar__foot">
            <span class="mbr-sidebar__user">{{ auth()->user()->alias }}</span>
            @can('admin.see-menu')
                <a href="{{ route('admin.dashboard') }}" class="mbr-sidebar__link">{{ __('booking.nav.admin') }}</a>
            @endcan
            <form method="POST" action="{{ route('logout') }}" class="mbr-sidebar__logout">
                @csrf
                <x-ui.button variant="text" type="submit" icon="logout" :block="false">{{ __('booking.nav.logout') }}</x-ui.button>
            </form>
        </div>
    @endauth
</aside>
