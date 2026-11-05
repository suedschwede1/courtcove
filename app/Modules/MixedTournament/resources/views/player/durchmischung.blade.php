{{--
    Durchmischung (right half of Turnier-Mixed-Wertung): mixing KPIs, rating, matrix of shared partnerships
    (partner counts as in ZF2, gap 2). Mexicano rounds pair by standings, so there is no rating there.
--}}
@extends('mixed::layout')

@section('title', __('mixed::common.tab_mixing').' · '.$event->title)

@section('mixed-content')
@php
    $ratingTone = ['good' => 'good', 'ok' => 'attention', 'poor' => 'problem', 'none' => 'neutral'];
    $rating = isset($ratingTone[$mixing['rating']]) ? $mixing['rating'] : 'none';
    // Small sand tiles inside the card (mockup), not the page-level x-ui.kpi figure.
    $tiles = [
        ['label' => __('mixed::common.kpi_mixing'), 'value' => __('mixed::common.percent', ['percent' => (int) $mixing['percent']]), 'meta' => null],
        ['label' => __('mixed::common.kpi_pairs'), 'value' => (int) $mixing['paired'], 'meta' => __('mixed::common.kpi_pairs_of', ['total' => (int) $mixing['total_pairs']])],
        ['label' => __('mixed::common.kpi_repeats'), 'value' => (int) $mixing['repeated'], 'meta' => null],
    ];
@endphp
@include('mixed::partials.event-head', ['event' => $event])

@include('mixed::partials.tabs', ['event' => $event, 'active' => 'durchmischung', 'canAdmin' => $canAdmin])

<x-ui.card :title="__('mixed::common.mixing_title')" class="w-full max-w-4xl">
    <div class="flex flex-col gap-3">
        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            @foreach ($tiles as $tile)
                <div class="flex flex-col gap-0.5 rounded-lg bg-ground p-2.5">
                    <dt class="text-body-s font-bold text-ink-2">{{ $tile['label'] }}</dt>
                    <dd class="font-display text-display-m tabular-nums text-ink">{{ $tile['value'] }}</dd>
                    @if ($tile['meta'] !== null)
                        <dd class="text-body-s text-ink-2">{{ $tile['meta'] }}</dd>
                    @endif
                </div>
            @endforeach
        </dl>
        <div>
            @if ($event->isMexicano())
                <x-ui.badge tone="neutral">{{ __('mixed::common.rating_mexicano') }}</x-ui.badge>
            @else
                <x-ui.badge :tone="$ratingTone[$rating]">{{ __('mixed::common.rating.'.$rating) }}</x-ui.badge>
            @endif
        </div>
        @if ($matrix['rows'] === [])
            <p class="text-body text-ink-2">{{ __('mixed::common.no_participants') }}</p>
        @else
            @include('mixed::partials.mixing-matrix', ['matrix' => $matrix])
        @endif
        <p class="text-body-s text-ink-2">{{ __('mixed::common.matrix_legend') }}</p>
    </div>
</x-ui.card>
@endsection
