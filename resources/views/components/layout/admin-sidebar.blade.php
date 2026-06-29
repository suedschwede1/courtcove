<aside class="w-[200px] min-h-screen bg-[#1b1d21] flex flex-col shrink-0">
    <div class="px-4 pt-5 pb-3 border-b border-white/8 text-[11px] uppercase tracking-[0.08em] text-[#6a6e73]" style="font-family: var(--font-body)">
        {{ __('booking.nav.admin') }}
    </div>

    <nav class="flex-1 py-2">
        <a href="{{ route('admin.dashboard') }}"
           @class([
               'flex items-center py-[9px] pl-[19px] pr-4 text-[13px] transition-colors border-l-[3px]',
               'text-white font-semibold bg-white/10 border-[#bf4316]' => request()->routeIs('admin.dashboard'),
               'text-[#a0a0a0] hover:text-white border-transparent' => !request()->routeIs('admin.dashboard'),
           ])
           style="font-family: var(--font-body)">{{ __('booking.admin.overview') }}</a>

        @if(Route::has('admin.users.index'))@can('admin.user')
        <a href="{{ route('admin.users.index') }}"
           @class([
               'flex items-center py-[9px] pl-[19px] pr-4 text-[13px] transition-colors border-l-[3px]',
               'text-white font-semibold bg-white/10 border-[#bf4316]' => request()->routeIs('admin.users.*'),
               'text-[#a0a0a0] hover:text-white border-transparent' => !request()->routeIs('admin.users.*'),
           ])
           style="font-family: var(--font-body)">{{ __('booking.admin.nav_users') }}</a>
        @endcan @endif

        @if(Route::has('admin.events.index'))@can('admin.event')
        <a href="{{ route('admin.events.index') }}"
           @class([
               'flex items-center py-[9px] pl-[19px] pr-4 text-[13px] transition-colors border-l-[3px]',
               'text-white font-semibold bg-white/10 border-[#bf4316]' => request()->routeIs('admin.events.*'),
               'text-[#a0a0a0] hover:text-white border-transparent' => !request()->routeIs('admin.events.*'),
           ])
           style="font-family: var(--font-body)">{{ __('booking.admin.nav_events') }}</a>
        @endcan @endif

        @if(Route::has('admin.bookings.index'))@can('admin.booking')
        <a href="{{ route('admin.bookings.index') }}"
           @class([
               'flex items-center py-[9px] pl-[19px] pr-4 text-[13px] transition-colors border-l-[3px]',
               'text-white font-semibold bg-white/10 border-[#bf4316]' => request()->routeIs('admin.bookings.*'),
               'text-[#a0a0a0] hover:text-white border-transparent' => !request()->routeIs('admin.bookings.*'),
           ])
           style="font-family: var(--font-body)">{{ __('booking.admin.nav_bookings') }}</a>
        @endcan @endif

        @if(Route::has('admin.squares.index'))@can('admin.config')
        <a href="{{ route('admin.squares.index') }}"
           @class([
               'flex items-center py-[9px] pl-[19px] pr-4 text-[13px] transition-colors border-l-[3px]',
               'text-white font-semibold bg-white/10 border-[#bf4316]' => request()->routeIs('admin.squares.*'),
               'text-[#a0a0a0] hover:text-white border-transparent' => !request()->routeIs('admin.squares.*'),
           ])
           style="font-family: var(--font-body)">{{ __('booking.admin.nav_courts') }}</a>
        @endcan @endif

        @if(Route::has('admin.config.edit'))@can('admin.config')
        <a href="{{ route('admin.config.edit') }}"
           @class([
               'flex items-center py-[9px] pl-[19px] pr-4 text-[13px] transition-colors border-l-[3px]',
               'text-white font-semibold bg-white/10 border-[#bf4316]' => request()->routeIs('admin.config.*'),
               'text-[#a0a0a0] hover:text-white border-transparent' => !request()->routeIs('admin.config.*'),
           ])
           style="font-family: var(--font-body)">{{ __('booking.admin.config') }}</a>
        @endcan @endif

        @if(Route::has('admin.testmail.index'))@can('admin.config')
        <a href="{{ route('admin.testmail.index') }}"
           @class([
               'flex items-center py-[9px] pl-[19px] pr-4 text-[13px] transition-colors border-l-[3px]',
               'text-white font-semibold bg-white/10 border-[#bf4316]' => request()->routeIs('admin.testmail.*'),
               'text-[#a0a0a0] hover:text-white border-transparent' => !request()->routeIs('admin.testmail.*'),
           ])
           style="font-family: var(--font-body)">Testmail</a>
        @endcan @endif
    </nav>

    <div class="p-4 border-t border-white/8">
        <a href="{{ route('calendar.index') }}" class="text-[#a0a0a0] hover:text-white text-sm transition-colors" style="font-family: var(--font-body)">← {{ __('booking.admin.to_calendar') }}</a>
    </div>
</aside>
