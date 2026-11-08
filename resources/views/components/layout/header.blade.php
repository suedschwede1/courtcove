@php
    $bookingName = trim((string) \App\Models\Option::getValue('service.name', config('booking.name')));
    $bookingName = $bookingName !== '' ? $bookingName : config('booking.name');

    $navigation = app(\App\Services\MainNavigation::class);
    $navItems = $navigation->items(auth()->user(), request());
    $barItems = $navigation->bar($navItems, request());
    $activeItem = collect($navItems)->first(fn ($item) => $item->active);
    $sectionTitle = $activeItem?->label ?? $bookingName;
@endphp
<header class="ds-header no-print" x-data="{ menuOpen: false }" @keydown.escape.window="if (menuOpen) { menuOpen = false; $refs.menuButton.focus() }" @resize.window="if (menuOpen && window.innerWidth >= 1024) menuOpen = false">
    <div class="ds-header__bar">
        <a href="{{ route('calendar.index') }}" class="ds-header__brand">
            <x-ui.logo-mark :name="$bookingName" />
            <span class="ds-header__titles">
                <span class="ds-header__club">{{ $bookingName }}</span>
            </span>
        </a>

        <span class="ds-header__section">{{ $sectionTitle }}</span>

        <nav class="ds-header__nav" aria-label="{{ __('booking.nav.main_menu') }}">
            <ul class="ds-header__list">
                @foreach($barItems as $item)
                    <li>
                        @if($item->children !== [])
                            <div class="ds-header__group" x-data="dsNavGroup" x-on:keydown.escape="close(true)" x-on:click.outside="close()" x-on:focusout="leave($event)" x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)">
                                <button type="button" class="ds-header__link ds-header__group-button" x-ref="trigger" x-on:click="toggle()" aria-controls="nav-group-{{ $item->key }}" aria-expanded="false" x-bind:aria-expanded="open ? 'true' : 'false'"@if($item->active) aria-current="true"@endif>{{ $item->label }}<x-ui.icon name="chevron-down" :size="16" class="ds-header__chevron" /></button>
                                <ul id="nav-group-{{ $item->key }}" class="ds-header__menu" x-show="open" x-cloak>
                                    @foreach($item->children as $child)
                                        <li><a href="{{ $child->url }}" class="ds-header__menu-link"@if($child->active) aria-current="page"@endif>{{ $child->label }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <a href="{{ $item->url }}" class="ds-header__link"@if($item->active) aria-current="page"@endif>{{ $item->label }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="ds-header__tools">
            @if(auth()->user()?->hasAnyPrivilege())
                <x-layout.admin-mode-switch />
            @endif
            @auth
                <x-layout.player-exchange-switch />
            @endauth
            <x-layout.locale-switch />
            @auth
                <x-ui.avatar :name="auth()->user()->alias" :href="route('account.edit')" :label="__('booking.nav.account_of', ['name' => auth()->user()->alias])" />
                <form method="POST" action="{{ route('logout') }}" class="ds-header__logout-form">
                    @csrf
                    <x-ui.button variant="text" type="submit" :block="false" class="ds-header__logout">{{ __('booking.nav.logout') }}</x-ui.button>
                </form>
            @else
                <x-ui.button :href="route('login', ['redirect_to' => url()->full()])" :block="false">{{ __('booking.nav.login') }}</x-ui.button>
            @endauth
        </div>

        <x-ui.icon-button icon="menu" variant="secondary" class="ds-header__menu-button" :label="__('booking.nav.open_menu')" x-ref="menuButton" x-on:click="menuOpen = true" aria-controls="phone-menu" x-bind:aria-expanded="menuOpen ? 'true' : 'false'" aria-expanded="false" />
    </div>

    <x-layout.phone-menu :items="$navItems" :club-name="$bookingName" />
</header>
