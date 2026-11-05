{{--
    Phone top bar: back arrow, optional eyebrow and the page title on the chrome colour, shown below 48rem only.
    A page puts it into the `phone-bar` section of layouts.app, which then hides the site header on phones so the
    bar takes its place (as in the phone mockups); desktop keeps the site header and the page's own head.
    Props: title (required), back (URL of the arrow; none without it), backLabel, eyebrow, heading (true: the title is
    the page's h1 on phones; the desktop head is hidden there, so there is one visible h1 at every width).
    The slot takes trailing actions (icon buttons, variant chrome).
--}}
@props(['title', 'back' => null, 'backLabel' => null, 'eyebrow' => null, 'heading' => false])
<header {{ $attributes->class(['ds-phone-bar', 'ds-on-chrome', 'no-print']) }}>
    @if ($back !== null)
        <x-ui.icon-button icon="chevron-left" variant="chrome" class="ds-phone-bar__lead" :href="$back" :label="$backLabel ?? __('booking.admin.nav.back')" />
    @endif
    <div class="ds-phone-bar__text">
        @if ($eyebrow !== null && $eyebrow !== '')
            <p class="ds-phone-bar__eyebrow">{{ $eyebrow }}</p>
        @endif
        @if ($heading)
            <h1 class="ds-phone-bar__title">{{ $title }}</h1>
        @else
            <p class="ds-phone-bar__title">{{ $title }}</p>
        @endif
    </div>
    {{ $slot }}
</header>
