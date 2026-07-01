@php
    $bookingName = trim((string) \App\Models\Option::getValue('service.name', config('booking.name')));
    $bookingName = $bookingName !== '' ? $bookingName : config('booking.name');
@endphp
<header x-data="{ actionsOpen: false }" @keydown.escape.window="actionsOpen = false" class="app-header no-print bg-[#eae8e2] px-3 py-3">
    <div class="app-header__card rounded-[6px] border border-[#d8d2c8] bg-white shadow-[0_2px_8px_rgba(0,0,0,0.08)]" @click.outside="actionsOpen = false">
        <div class="app-header__inner flex items-center gap-6 px-5 py-4">
            <a href="{{ route('calendar.index') }}" aria-label="{{ $bookingName }}" class="app-header__brand flex min-w-0 shrink-0 items-center gap-4">
                @if($bookingLogoPath && file_exists(public_path($bookingLogoPath)))
                    <img src="{{ asset($bookingLogoPath) }}"
                         width="112"
                         height="108"
                         alt="{{ $bookingName }}"
                         class="app-header__logo object-contain"
                         style="width: 112px; height: 108px;">
                @endif
                <span class="app-header__title text-[18px] font-bold leading-tight text-[#151515]" style="font-family: var(--font-display)">{{ $bookingName }}</span>
            </a>

            <div class="app-header__nav-wrap flex flex-1 justify-center">
                <div class="app-header__nav ui-calendar-nav" id="calendar-header-nav">
                    @stack('header-nav')
                </div>
            </div>

            <div class="app-header__mobile-controls">
                <button type="button"
                        class="app-header__action-trigger"
                        @click="actionsOpen = !actionsOpen"
                        :aria-expanded="actionsOpen ? 'true' : 'false'"
                        aria-controls="app-header-actions"
                        aria-label="{{ __('booking.nav.more_actions') }}"
                        title="{{ __('booking.nav.more_actions') }}">...</button>
            </div>

            <div id="app-header-actions" class="app-header__actions ml-auto flex shrink-0 items-center gap-2" :class="{ 'is-open': actionsOpen }">
                @hasSection('calendar-system-info')
                    <button type="button"
                            class="inline-flex h-8 items-center rounded-[6px] border border-[#d4cec3] bg-white px-4 text-[13px] font-medium text-[#6a6e73] transition-colors hover:border-[#bf4316] hover:text-[#bf4316]"
                            data-panel-toggle="system-info-panel">{{ __('booking.nav.info') }}</button>
                @endif
                @hasSection('calendar-help')
                    <button type="button"
                            class="inline-flex h-8 items-center rounded-[6px] border border-[#d4cec3] bg-white px-4 text-[13px] font-medium text-[#6a6e73] transition-colors hover:border-[#bf4316] hover:text-[#bf4316]"
                            data-panel-toggle="help-panel">{{ __('booking.nav.help') }}</button>
                @endif

                @auth
                    <a href="{{ route('account.bookings') }}"
                       class="inline-flex h-8 items-center rounded-[6px] border border-[#d4cec3] bg-white px-4 text-[13px] font-medium text-[#6a6e73] transition-colors hover:border-[#bf4316] hover:text-[#bf4316]">{{ __('booking.nav.my_bookings') }}</a>
                    <a href="{{ route('account.edit') }}"
                       class="inline-flex h-8 items-center rounded-[6px] border border-[#d4cec3] bg-white px-4 text-[13px] font-medium text-[#6a6e73] transition-colors hover:border-[#bf4316] hover:text-[#bf4316]">{{ __('booking.nav.my_account') }}</a>
                    @can('admin.see-menu')
                        <a href="{{ route('admin.dashboard') }}"
                           class="inline-flex h-8 items-center rounded-[6px] border border-[#d4cec3] bg-white px-4 text-[13px] font-medium text-[#6a6e73] transition-colors hover:border-[#bf4316] hover:text-[#bf4316]">{{ __('booking.nav.admin') }}</a>
                    @endcan
                    <form method="POST" action="{{ route('logout') }}" class="m-0 inline app-header__desktop-logout">
                        @csrf
                        <button type="submit"
                                class="inline-flex h-8 items-center rounded-[6px] border border-[#d4cec3] bg-white px-4 text-[13px] font-medium text-[#6a6e73] transition-colors hover:border-[#bf4316] hover:text-[#bf4316]">{{ __('booking.nav.logout') }}</button>
                    </form>
                @else
                    <a href="{{ route('login', ['redirect_to' => url()->full()]) }}"
                       class="header-login-button inline-flex h-8 items-center rounded-[6px] bg-[#bf4316] px-4 text-[13px] font-semibold text-white transition-colors hover:bg-[#9e3412]">{{ __('booking.nav.login') }}</a>
                @endauth

                <div class="app-header__locale flex items-center gap-1 text-[13px] font-medium text-[#6a6e73]">
                    @foreach(config('app.available_locales') as $loc)
                        @if(! $loop->first)
                            <span aria-hidden="true">|</span>
                        @endif
                        @if($loc === app()->getLocale())
                            <span class="font-bold text-[#151515]">{{ strtoupper($loc) }}</span>
                        @else
                            <a href="{{ route('lang.switch', ['locale' => $loc]) }}" class="hover:text-[#bf4316]">{{ strtoupper($loc) }}</a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</header>
