@props(['items', 'clubName'])
<div id="phone-menu" class="ds-phone-menu ds-on-chrome" role="dialog" aria-modal="true" aria-label="{{ __('booking.nav.phone_menu') }}"
     x-show="menuOpen" x-cloak x-transition.opacity
     x-effect="document.documentElement.classList.toggle('ds-scroll-lock', menuOpen); if (menuOpen) $nextTick(() => $refs.menuClose.focus())"
     @keydown.tab="const f = [...$el.querySelectorAll('a[href], button:not([disabled])')].filter(e => e.offsetParent !== null); if (f.length) { if ($event.shiftKey && document.activeElement === f[0]) { $event.preventDefault(); f[f.length - 1].focus() } else if (!$event.shiftKey && document.activeElement === f[f.length - 1]) { $event.preventDefault(); f[0].focus() } }">
    <div class="ds-phone-menu__top">
        <x-ui.logo-mark :name="$clubName" :size="36" />
        <span class="ds-phone-menu__club">{{ $clubName }}</span>
        <x-ui.icon-button icon="close" variant="chrome" :label="__('booking.nav.close_menu')" x-ref="menuClose" x-on:click="menuOpen = false; $refs.menuButton.focus()" />
    </div>

    @auth
        <div class="ds-phone-menu__user">
            <x-ui.avatar :name="auth()->user()->alias" class="ds-phone-menu__avatar" />
            <span class="ds-phone-menu__who">
                <span class="ds-phone-menu__alias">{{ auth()->user()->alias }}</span>
                <span class="ds-phone-menu__status">{{ __('booking.nav.logged_in') }}</span>
            </span>
        </div>
    @endauth

    <nav class="ds-phone-menu__nav" aria-label="{{ __('booking.nav.phone_menu') }}">
        @foreach($items as $item)
            <a href="{{ $item->url }}" class="ds-phone-menu__link"@if($item->active) aria-current="page"@endif><x-ui.icon :name="$item->icon" :size="22" /><span>{{ $item->label }}</span></a>
        @endforeach
    </nav>

    <div class="ds-phone-menu__rows">
        @if(auth()->user()?->hasAnyPrivilege())
            <div class="ds-phone-menu__row">
                <x-layout.admin-mode-switch variant="menu" />
            </div>
        @endif
        <div class="ds-phone-menu__row">
            <span class="ds-phone-menu__row-label">{{ __('booking.nav.language') }}</span>
            <x-layout.locale-switch variant="menu" />
        </div>
    </div>

    <div class="ds-phone-menu__foot">
        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.button variant="secondary" type="submit" icon="logout" class="ds-phone-menu__logout">{{ __('booking.nav.logout') }}</x-ui.button>
            </form>
        @else
            <x-ui.button :href="route('login', ['redirect_to' => url()->full()])" class="ds-phone-menu__login">{{ __('booking.nav.login') }}</x-ui.button>
        @endauth
    </div>
</div>
