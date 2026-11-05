@php
    $bookingName = trim((string) \App\Models\Option::getValue('service.name', config('booking.name')));
    $bookingName = $bookingName !== '' ? $bookingName : config('booking.name');

    $navItems = app(\App\Services\MainNavigation::class)->items(auth()->user(), request());
    $activeItem = collect($navItems)->first(fn ($item) => $item->active);
    $sectionTitle = $activeItem?->label ?? $bookingName;
@endphp
<header class="ds-header ds-on-chrome no-print" x-data="{ menuOpen: false }" @keydown.escape.window="if (menuOpen) { menuOpen = false; $refs.menuButton.focus() }" @resize.window="if (menuOpen && window.innerWidth >= 768) menuOpen = false">
    <div class="ds-header__bar">
        <a href="{{ route('calendar.index') }}" class="ds-header__brand">
            <x-ui.logo-mark :name="$bookingName" />
            <span class="ds-header__titles">
                <span class="ds-header__club">{{ $bookingName }}</span>
                <span class="ds-header__eyebrow">{{ __('booking.nav.eyebrow') }}</span>
            </span>
        </a>

        <span class="ds-header__section">{{ $sectionTitle }}</span>

        <nav class="ds-header__nav" aria-label="{{ __('booking.nav.main_menu') }}">
            @foreach($navItems as $item)
                @if($item->inBar)
                    <a href="{{ $item->url }}" class="ds-header__link"@if($item->active) aria-current="page"@endif>{{ $item->label }}</a>
                @endif
            @endforeach
        </nav>

        <div class="ds-header__tools">
            @can('admin.booking')
                <x-layout.admin-mode-switch />
            @endcan
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

        <x-ui.icon-button icon="menu" variant="chrome" class="ds-header__menu-button" :label="__('booking.nav.open_menu')" x-ref="menuButton" x-on:click="menuOpen = true" aria-controls="phone-menu" x-bind:aria-expanded="menuOpen ? 'true' : 'false'" aria-expanded="false" />
    </div>

    <x-layout.phone-menu :items="$navItems" :club-name="$bookingName" />
</header>
