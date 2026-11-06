{{--
    The button that opens the area sidebar as a slide-in on phones (navOpen of layouts.admin). The shell's phone bar
    leads with it; a page bringing its own phone bar (section phone-bar, x-layout.phone-bar) puts it into the bar's
    slot, so every page of an area keeps the menu. Focus moves to the sidebar's close button and back on close.
--}}
<x-ui.icon-button icon="menu" variant="chrome" {{ $attributes->class(['ds-area-menu-button']) }} :label="__('booking.admin.nav.open')" x-ref="navButton" x-on:click="navOpen = true; $nextTick(() => $refs.navClose?.focus())" aria-controls="admin-nav" aria-expanded="false" x-bind:aria-expanded="navOpen ? 'true' : 'false'" />
