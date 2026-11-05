{{--
    Schedule (old index/schedule.phtml): status chips (keeping the area), area chips (keeping the status),
    the count line and one match card per match, newest date first. Actions only where the viewer may write.
--}}
@extends('tournament::layout')

@section('tournament-content')
@php
    use App\Modules\Tournament\Support\MatchPresenter;

    $presenter = app(MatchPresenter::class);
    $query = $group !== '' ? '?grp='.urlencode($group) : '';
    $base = route('tournament.schedule', ['filter' => $filter]);
@endphp

<nav aria-label="{{ __('tournament::schedule.filter_label') }}" class="flex flex-wrap gap-2">
    @foreach ($filters as $key)
        <x-ui.chip :href="route('tournament.schedule', ['filter' => $key]).$query" :active="$key === $filter">{{ __('tournament::schedule.filters.'.$key) }}</x-ui.chip>
    @endforeach
</nav>

@if (count($groups) > 1)
    <nav aria-label="{{ __('tournament::schedule.area_label') }}" class="flex flex-wrap gap-2">
        <x-ui.chip :href="$base" :active="$group === ''">{{ __('tournament::schedule.all_areas') }}</x-ui.chip>
        @foreach ($groups as $key => $label)
            <x-ui.chip :href="$base.'?grp='.urlencode((string) $key)" :active="(string) $key === $group">{{ str_starts_with((string) $key, 'KO') ? $label : $key }}</x-ui.chip>
        @endforeach
    </nav>
@endif

<p class="text-body-s text-ink-2">
    {{ __('tournament::schedule.count', ['shown' => count($matches), 'total' => (int) $counts['total']]) }}@if ($group !== '') · {{ $groups[$group] ?? $group }}@endif · {{ __('tournament::schedule.states', ['played' => (int) $counts['played'], 'scheduled' => (int) $counts['scheduled'], 'open' => (int) $counts['open']]) }}
</p>

@if (! $matches)
    <p class="text-ink-2">{{ __('tournament::schedule.empty') }}</p>
@else
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($matches as $match)
            @include('tournament::partials.match-card', ['props' => $presenter->present($match, $players, $access, $tournament)])
        @endforeach
    </div>
@endif
@endsection
