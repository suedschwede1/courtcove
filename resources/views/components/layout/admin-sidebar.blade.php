<aside style="width:180px; min-height:100vh; background:#1b1d21; flex-shrink:0; display:flex; flex-direction:column;">
    <div style="padding:14px 14px 10px; border-bottom:1px solid rgba(255,255,255,0.08); margin-bottom:8px;">
        <span style="font-family:var(--font-body); font-size:10px; color:#6a6e73; font-weight:600; text-transform:uppercase; letter-spacing:0.08em;">
            {{ __('booking.nav.admin') }}
        </span>
    </div>

    <nav style="flex:1; padding:6px 0;">
        @can('admin.see-menu')
            <a href="{{ route('admin.dashboard') }}" onmouseover="if (!this.dataset.active) { this.style.background='#26292e'; this.style.color='#ffffff'; }" onmouseout="if (!this.dataset.active) { this.style.background='transparent'; this.style.color='#b2b6bd'; }" data-active="{{ request()->routeIs('admin.dashboard') ? '1' : '' }}" style="display:block; padding:12px 14px 12px {{ request()->routeIs('admin.dashboard') ? '11px' : '17px' }}; font-family:var(--font-body); font-size:14px; text-decoration:none; transition:background 0.15s ease, color 0.15s ease; color:{{ request()->routeIs('admin.dashboard') ? '#ffffff' : '#b2b6bd' }}; font-weight:{{ request()->routeIs('admin.dashboard') ? '700' : '400' }}; background:{{ request()->routeIs('admin.dashboard') ? '#34363b' : 'transparent' }}; border-left:{{ request()->routeIs('admin.dashboard') ? '3px solid #bf4316' : '3px solid transparent' }};">
                {{ __('booking.admin.dashboard.heading') }}
            </a>
        @endcan

        @can('admin.user')
            <a href="{{ route('admin.users.index') }}" onmouseover="if (!this.dataset.active) { this.style.background='#26292e'; this.style.color='#ffffff'; }" onmouseout="if (!this.dataset.active) { this.style.background='transparent'; this.style.color='#b2b6bd'; }" data-active="{{ request()->routeIs('admin.users.*') ? '1' : '' }}" style="display:block; padding:12px 14px 12px {{ request()->routeIs('admin.users.*') ? '11px' : '17px' }}; font-family:var(--font-body); font-size:14px; text-decoration:none; transition:background 0.15s ease, color 0.15s ease; color:{{ request()->routeIs('admin.users.*') ? '#ffffff' : '#b2b6bd' }}; font-weight:{{ request()->routeIs('admin.users.*') ? '700' : '400' }}; background:{{ request()->routeIs('admin.users.*') ? '#34363b' : 'transparent' }}; border-left:{{ request()->routeIs('admin.users.*') ? '3px solid #bf4316' : '3px solid transparent' }};">
                {{ __('booking.admin.users.members') }}
            </a>
        @endcan

        @can('admin.booking')
            <a href="{{ route('admin.bookings.index') }}" onmouseover="if (!this.dataset.active) { this.style.background='#26292e'; this.style.color='#ffffff'; }" onmouseout="if (!this.dataset.active) { this.style.background='transparent'; this.style.color='#b2b6bd'; }" data-active="{{ request()->routeIs('admin.bookings.*') ? '1' : '' }}" style="display:block; padding:12px 14px 12px {{ request()->routeIs('admin.bookings.*') ? '11px' : '17px' }}; font-family:var(--font-body); font-size:14px; text-decoration:none; transition:background 0.15s ease, color 0.15s ease; color:{{ request()->routeIs('admin.bookings.*') ? '#ffffff' : '#b2b6bd' }}; font-weight:{{ request()->routeIs('admin.bookings.*') ? '700' : '400' }}; background:{{ request()->routeIs('admin.bookings.*') ? '#34363b' : 'transparent' }}; border-left:{{ request()->routeIs('admin.bookings.*') ? '3px solid #bf4316' : '3px solid transparent' }};">
                {{ __('booking.admin.bookings.title') }}
            </a>
        @endcan

        @can('admin.booking')
            <a href="{{ route('admin.statistics.index') }}" onmouseover="if (!this.dataset.active) { this.style.background='#26292e'; this.style.color='#ffffff'; }" onmouseout="if (!this.dataset.active) { this.style.background='transparent'; this.style.color='#b2b6bd'; }" data-active="{{ request()->routeIs('admin.statistics.*') ? '1' : '' }}" style="display:block; padding:12px 14px 12px {{ request()->routeIs('admin.statistics.*') ? '11px' : '17px' }}; font-family:var(--font-body); font-size:14px; text-decoration:none; transition:background 0.15s ease, color 0.15s ease; color:{{ request()->routeIs('admin.statistics.*') ? '#ffffff' : '#b2b6bd' }}; font-weight:{{ request()->routeIs('admin.statistics.*') ? '700' : '400' }}; background:{{ request()->routeIs('admin.statistics.*') ? '#34363b' : 'transparent' }}; border-left:{{ request()->routeIs('admin.statistics.*') ? '3px solid #bf4316' : '3px solid transparent' }};">
                {{ __('booking.admin.statistics.title') }}
            </a>
        @endcan

        @can('admin.event')
            <a href="{{ route('admin.events.index') }}" onmouseover="if (!this.dataset.active) { this.style.background='#26292e'; this.style.color='#ffffff'; }" onmouseout="if (!this.dataset.active) { this.style.background='transparent'; this.style.color='#b2b6bd'; }" data-active="{{ request()->routeIs('admin.events.*') ? '1' : '' }}" style="display:block; padding:12px 14px 12px {{ request()->routeIs('admin.events.*') ? '11px' : '17px' }}; font-family:var(--font-body); font-size:14px; text-decoration:none; transition:background 0.15s ease, color 0.15s ease; color:{{ request()->routeIs('admin.events.*') ? '#ffffff' : '#b2b6bd' }}; font-weight:{{ request()->routeIs('admin.events.*') ? '700' : '400' }}; background:{{ request()->routeIs('admin.events.*') ? '#34363b' : 'transparent' }}; border-left:{{ request()->routeIs('admin.events.*') ? '3px solid #bf4316' : '3px solid transparent' }};">
                {{ __('booking.admin.events.title') }}
            </a>
        @endcan

        @can('admin.config')
            <a href="{{ route('admin.squares.index') }}" onmouseover="if (!this.dataset.active) { this.style.background='#26292e'; this.style.color='#ffffff'; }" onmouseout="if (!this.dataset.active) { this.style.background='transparent'; this.style.color='#b2b6bd'; }" data-active="{{ request()->routeIs('admin.squares.*') ? '1' : '' }}" style="display:block; padding:12px 14px 12px {{ request()->routeIs('admin.squares.*') ? '11px' : '17px' }}; font-family:var(--font-body); font-size:14px; text-decoration:none; transition:background 0.15s ease, color 0.15s ease; color:{{ request()->routeIs('admin.squares.*') ? '#ffffff' : '#b2b6bd' }}; font-weight:{{ request()->routeIs('admin.squares.*') ? '700' : '400' }}; background:{{ request()->routeIs('admin.squares.*') ? '#34363b' : 'transparent' }}; border-left:{{ request()->routeIs('admin.squares.*') ? '3px solid #bf4316' : '3px solid transparent' }};">
                {{ __('booking.admin.squares.title') }}
            </a>
        @endcan

        @can('admin.config')
            <a href="{{ route('admin.database.index') }}" onmouseover="if (!this.dataset.active) { this.style.background='#26292e'; this.style.color='#ffffff'; }" onmouseout="if (!this.dataset.active) { this.style.background='transparent'; this.style.color='#b2b6bd'; }" data-active="{{ request()->routeIs('admin.database.*') ? '1' : '' }}" style="display:block; padding:12px 14px 12px {{ request()->routeIs('admin.database.*') ? '11px' : '17px' }}; font-family:var(--font-body); font-size:14px; text-decoration:none; transition:background 0.15s ease, color 0.15s ease; color:{{ request()->routeIs('admin.database.*') ? '#ffffff' : '#b2b6bd' }}; font-weight:{{ request()->routeIs('admin.database.*') ? '700' : '400' }}; background:{{ request()->routeIs('admin.database.*') ? '#34363b' : 'transparent' }}; border-left:{{ request()->routeIs('admin.database.*') ? '3px solid #bf4316' : '3px solid transparent' }};">
                {{ __('booking.admin.database.title') }}
            </a>
        @endcan

        @can('admin.config')
            <a href="{{ route('admin.config.edit') }}" onmouseover="if (!this.dataset.active) { this.style.background='#26292e'; this.style.color='#ffffff'; }" onmouseout="if (!this.dataset.active) { this.style.background='transparent'; this.style.color='#b2b6bd'; }" data-active="{{ request()->routeIs('admin.config.edit') || request()->routeIs('admin.config.update') ? '1' : '' }}" style="display:block; padding:12px 14px 12px {{ request()->routeIs('admin.config.edit') || request()->routeIs('admin.config.update') ? '11px' : '17px' }}; font-family:var(--font-body); font-size:14px; text-decoration:none; transition:background 0.15s ease, color 0.15s ease; color:{{ request()->routeIs('admin.config.edit') || request()->routeIs('admin.config.update') ? '#ffffff' : '#b2b6bd' }}; font-weight:{{ request()->routeIs('admin.config.edit') || request()->routeIs('admin.config.update') ? '700' : '400' }}; background:{{ request()->routeIs('admin.config.edit') || request()->routeIs('admin.config.update') ? '#34363b' : 'transparent' }}; border-left:{{ request()->routeIs('admin.config.edit') || request()->routeIs('admin.config.update') ? '3px solid #bf4316' : '3px solid transparent' }};">
                {{ __('booking.admin.texts') }}
            </a>
        @endcan

        @can('admin.config')
            <a href="{{ route('admin.config.behavior.edit') }}" onmouseover="if (!this.dataset.active) { this.style.background='#26292e'; this.style.color='#ffffff'; }" onmouseout="if (!this.dataset.active) { this.style.background='transparent'; this.style.color='#b2b6bd'; }" data-active="{{ request()->routeIs('admin.config.behavior.*') ? '1' : '' }}" style="display:block; padding:12px 14px 12px {{ request()->routeIs('admin.config.behavior.*') ? '11px' : '17px' }}; font-family:var(--font-body); font-size:14px; text-decoration:none; transition:background 0.15s ease, color 0.15s ease; color:{{ request()->routeIs('admin.config.behavior.*') ? '#ffffff' : '#b2b6bd' }}; font-weight:{{ request()->routeIs('admin.config.behavior.*') ? '700' : '400' }}; background:{{ request()->routeIs('admin.config.behavior.*') ? '#34363b' : 'transparent' }}; border-left:{{ request()->routeIs('admin.config.behavior.*') ? '3px solid #bf4316' : '3px solid transparent' }};">
                {{ __('booking.admin.behavior') }}
            </a>
        @endcan

        <a href="{{ route('calendar.index') }}" onmouseover="this.style.background='#26292e'; this.style.color='#ffffff';" onmouseout="this.style.background='transparent'; this.style.color='#8f949c';" style="display:block; padding:12px 14px 12px 19px; font-family:var(--font-body); font-size:14px; text-decoration:none; transition:background 0.15s ease, color 0.15s ease; color:#8f949c; font-weight:400; background:transparent; border-left:3px solid transparent;">
            ← {{ __('booking.admin.to_calendar') }}
        </a>
    </nav>
</aside>

