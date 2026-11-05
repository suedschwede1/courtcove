@props(['paginator'])
@php
    $last = $paginator->lastPage();
    $current = $paginator->currentPage();
    $pages = [];
    $previous = null;
    for ($i = 1; $i <= $last; $i++) {
        if ($i === 1 || $i === $last || abs($i - $current) <= 1) {
            if ($previous !== null && $i - $previous > 1) {
                $pages[] = null;
            }
            $pages[] = $i;
            $previous = $i;
        }
    }
@endphp
@if ($last > 1)
<nav {{ $attributes->class(['ds-pagination']) }} aria-label="{{ __('booking.ui.pagination.label') }}">
@if ($paginator->onFirstPage())
<span class="ds-pagination__item ds-pagination__item--disabled" aria-hidden="true"><x-ui.icon name="chevron-left" /></span>
@else
<a class="ds-pagination__item" href="{{ $paginator->url($current - 1) }}" rel="prev" aria-label="{{ __('booking.ui.pagination.previous') }}"><x-ui.icon name="chevron-left" /></a>
@endif
@foreach ($pages as $page)
@if ($page === null)
<span class="ds-pagination__gap" aria-hidden="true">…</span>
@elseif ($page === $current)
<span class="ds-pagination__page" aria-current="page">{{ $page }}</span>
@else
<a class="ds-pagination__item" href="{{ $paginator->url($page) }}" aria-label="{{ __('booking.ui.pagination.page', ['page' => $page]) }}">{{ $page }}</a>
@endif
@endforeach
@if ($paginator->hasMorePages())
<a class="ds-pagination__item" href="{{ $paginator->url($current + 1) }}" rel="next" aria-label="{{ __('booking.ui.pagination.next') }}"><x-ui.icon name="chevron-right" /></a>
@else
<span class="ds-pagination__item ds-pagination__item--disabled" aria-hidden="true"><x-ui.icon name="chevron-right" /></span>
@endif
</nav>
@endif
