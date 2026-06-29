@php
    $bookingName = trim((string) \App\Models\Option::getValue('service.name', config('booking.name')));
    $bookingName = $bookingName !== '' ? $bookingName : config('booking.name');
@endphp
<header class="no-print bg-[#eae8e2] px-3 py-3">
    <div class="rounded-[6px] border border-[#d8d2c8] bg-white shadow-[0_2px_8px_rgba(0,0,0,0.08)]">
        <div class="flex items-center gap-6 px-5 py-4">
            <a href="{{ route('calendar.index') }}" aria-label="{{ $bookingName }}" class="flex min-w-0 shrink-0 items-center gap-4">
                <img src="{{ asset(config('booking.logo_path')) }}"
                     width="{{ config('booking.logo_width') }}"
                     height="{{ config('booking.logo_height') }}"
                     alt="{{ $bookingName }}"
                     class="block shrink-0 object-contain">
                <span class="text-[18px] font-bold leading-tight text-[#151515]" style="font-family: var(--font-display)">{{ $bookingName }}</span>
            </a>

            <div class="flex flex-1 justify-center">
                <div class="ui-calendar-nav" id="calendar-header-nav">
                    @stack('header-nav')
                </div>
            </div>

            <div class="ml-auto flex shrink-0 items-center gap-2">
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
                    <form method="POST" action="{{ route('logout') }}" class="m-0 inline">
                        @csrf
                        <button type="submit"
                                class="inline-flex h-8 items-center rounded-[6px] border border-[#d4cec3] bg-white px-4 text-[13px] font-medium text-[#6a6e73] transition-colors hover:border-[#bf4316] hover:text-[#bf4316]">{{ __('booking.nav.logout') }}</button>
                    </form>
                @else
                    <a href="{{ route('login', ['redirect_to' => url()->full()]) }}"
                       class="header-login-button inline-flex h-8 items-center rounded-[6px] bg-[#bf4316] px-4 text-[13px] font-semibold text-white transition-colors hover:bg-[#9e3412]">{{ __('booking.nav.login') }}</a>
                @endauth
            </div>
        </div>
    </div>
</header>
