{{--
    Head of every Members page: eyebrow, the page's only h1 and actions. The area navigation lives in the sidebar of
    the admin shell (members::layout). On phones the shell's top bar shows eyebrow and title, so both step aside
    (the h1 stays for screen readers). Slot phoneAction: the primary action as a full-width button pinned to the
    bottom on phones (the matching header button carries mbr-phone-hidden).
--}}
@props(['title', 'eyebrow' => __('members::admin.area.eyebrow')])
<header {{ $attributes->class('mbr-page-header') }}>
    <div class="mbr-page-header__text">
        <p class="mbr-page-header__eyebrow">{{ $eyebrow }}</p>
        <h1 class="mbr-page-header__title">{{ $title }}</h1>
    </div>
    @if(isset($actions) && $actions->isNotEmpty())
        <div class="mbr-page-header__actions">{{ $actions }}</div>
    @endif
</header>
@if(isset($phoneAction) && $phoneAction->isNotEmpty())
    @push('members-phone-action')
        <div class="ds-phone-actionbar-spacer" aria-hidden="true"></div>
        <div class="ds-phone-actionbar mbr-phone-action">{{ $phoneAction }}</div>
    @endpush
@endif
