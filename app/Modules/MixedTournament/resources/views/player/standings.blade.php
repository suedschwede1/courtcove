{{-- Standings (left half of Turnier-Mixed-Wertung; the matrix half lives on the Durchmischung URL). --}}
@extends(! empty($adminView) ? 'mixed::admin.layout' : 'mixed::layout')

@section('title', __('mixed::common.tab_standings').' · '.$event->title)

@section('mixed-content')
@php
    use App\Modules\MixedTournament\Support\MixedFormat;

    $first = $standings[0] ?? null;
    $leader = $first !== null && $first['eligible']
        ? __('mixed::common.leader_value', ['name' => $labels[(int) $first['id']] ?? __('mixed::common.unknown_player'), 'avg' => MixedFormat::decimal($first['points_avg'])])
        : '–';
@endphp
@if (! empty($adminView))
    @include('mixed::admin.partials.event-head')
@else
    @include('mixed::partials.event-head', ['event' => $event])
@endif

@include('mixed::partials.tabs', ['event' => $event, 'active' => 'wertung', 'canAdmin' => $canAdmin, 'adminView' => ! empty($adminView)])

<section aria-label="{{ __('mixed::common.standings_title') }}" class="flex w-full max-w-4xl flex-col gap-3">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {{-- Key figure tiles as cards with eyebrow (the eyebrow colour on chrome comes from the card tone). --}}
        <x-ui.card tone="highlight" as="div" :eyebrow="__('mixed::common.kpi_leader')">
            <p class="font-display text-display-l tabular-nums">{{ $leader }}</p>
        </x-ui.card>
        <x-ui.card as="div" :eyebrow="__('mixed::common.kpi_participants')">
            <p class="font-display text-display-l tabular-nums text-ink">{{ count($standings) }}</p>
        </x-ui.card>
    </div>
    <x-ui.notice tone="good">{{ __('mixed::common.eligibility') }}</x-ui.notice>
    @include('mixed::partials.standings-table', ['standings' => $standings, 'labels' => $labels])
</section>
@endsection
