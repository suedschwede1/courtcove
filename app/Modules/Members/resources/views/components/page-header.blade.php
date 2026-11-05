@props(['title', 'eyebrow' => __('booking.nav.members')])
<header {{ $attributes->class('mbr-page-header') }}>
    <div class="mbr-page-header__text">
        <p class="mbr-page-header__eyebrow">{{ $eyebrow }}</p>
        <h1 class="mbr-page-header__title">{{ $title }}</h1>
    </div>
    @if(isset($actions) && $actions->isNotEmpty())
        <div class="mbr-page-header__actions">{{ $actions }}</div>
    @endif
</header>
