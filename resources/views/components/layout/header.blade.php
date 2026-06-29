@php
    $bookingName = trim((string) \App\Models\Option::getValue('service.name', config('booking.name')));
    $bookingName = $bookingName !== '' ? $bookingName : config('booking.name');
@endphp
<header class="no-print bg-[#eae8e2] px-3 py-3">
    <div class="bg-white border border-[#d1cbc0] rounded-lg flex items-center gap-4 px-4 py-3">

        {{-- Logo + Name --}}
        <a href="{{ route('calendar.index') }}"
           aria-label="{{ $bookingName }}"
           class="flex items-center gap-3 shrink-0">
            <img src="{{ asset(config('booking.logo_path')) }}"
                 width="{{ config('booking.logo_width') }}"
                 height="{{ config('booking.logo_height') }}"
                 alt="{{ $bookingName }}"
                 class="block">
            <span class="text-[#151515] font-bold text-lg leading-tight"
                  style="font-family: var(--font-display)">{{ $bookingName }}</span>
        </a>

        {{-- Datum-Navigation (Mitte) --}}
        <div class="flex items-center gap-1">
            @stack('header-nav')
        </div>

        {{-- Action-Buttons (rechts) --}}
        <div class="flex items-center gap-1 ml-auto">
            @hasSection('calendar-system-info')
                <button type="button"
                        class="text-sm font-medium text-[#151515] hover:text-[#bf4316] transition-colors px-3 py-1.5 border border-[#d1cbc0] rounded hover:bg-[#f9f8f6] header-help-toggle"
                        data-panel-toggle="system-info-panel">{{ __('booking.nav.info') }}</button>
            @endif
            @hasSection('calendar-help')
                <button type="button"
                        class="text-sm font-medium text-[#151515] hover:text-[#bf4316] transition-colors px-3 py-1.5 border border-[#d1cbc0] rounded hover:bg-[#f9f8f6] header-help-toggle"
                        data-panel-toggle="help-panel">{{ __('booking.nav.help') }}</button>
            @endif
            @auth
                <a href="{{ route('account.bookings') }}"
                   class="text-sm font-medium text-[#151515] hover:text-[#bf4316] transition-colors px-2 py-1">{{ __('booking.nav.my_bookings') }}</a>
                <a href="{{ route('account.edit') }}"
                   class="text-sm font-medium text-[#151515] hover:text-[#bf4316] transition-colors px-2 py-1">{{ __('booking.nav.my_account') }}</a>
                @can('admin.see-menu')
                    <a href="{{ route('admin.dashboard') }}"
                       class="text-sm font-medium text-[#151515] hover:text-[#bf4316] transition-colors px-2 py-1">{{ __('booking.nav.admin') }}</a>
                @endcan
                <form method="POST" action="{{ route('logout') }}" class="inline m-0">
                    @csrf
                    <button type="submit"
                            class="text-sm font-medium text-[#151515] hover:text-[#bf4316] transition-colors px-2 py-1">{{ __('booking.nav.logout') }}</button>
                </form>
                <a href="#"
                   class="text-sm font-medium text-[#151515] hover:text-[#bf4316] transition-colors px-2 py-1 header-help">?</a>
            @else
                <a href="{{ route('login', ['redirect_to' => url()->full()]) }}"
                   class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-4 py-2 rounded transition-colors">{{ __('booking.nav.login') }}</a>
            @endauth
        </div>

    </div>
</header>
