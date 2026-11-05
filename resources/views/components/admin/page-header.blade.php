@props(['title', 'eyebrow' => null, 'backUrl' => null, 'backLabel' => null, 'actions' => null])
@php
    $showCrumbs = $backUrl !== null && $backUrl !== '' && ! request('popup');
    $hasMeta = $slot->isNotEmpty();
    $hasActions = $actions !== null && trim((string) $actions) !== '';
    // In the popup the frame header already is the h1.
    $titleTag = request('popup') ? 'h2' : 'h1';
@endphp
<div class="ds-admin-head">
    @if ($showCrumbs)
        <nav class="ds-admin-crumbs" aria-label="{{ __('booking.admin.nav.breadcrumb') }}">
            <a href="{{ $backUrl }}">{{ $backLabel ?? __('booking.admin.nav.back') }}</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $title }}</span>
        </nav>
    @endif
    <div class="ds-admin-head__text">
        @if ($eyebrow !== null && $eyebrow !== '')
            <p class="ds-admin-head__eyebrow">{{ $eyebrow }}</p>
        @endif
        <{{ $titleTag }} class="ds-admin-head__title">{{ $title }}</{{ $titleTag }}>
        @if ($hasMeta)
            <div class="ds-admin-head__meta">{{ $slot }}</div>
        @endif
    </div>
    @if ($hasActions)
        <div class="ds-admin-head__actions">{{ $actions }}</div>
    @endif
</div>
