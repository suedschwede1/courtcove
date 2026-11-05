@extends('pyramid::layout')

@section('title', __('pyramid::ui.challenges.title'))

@section('pyramid-content')
@php
    use App\Modules\Pyramid\Support\PyramidFormat;
    use App\Modules\Pyramid\Support\PyramidLinks;
    use App\Modules\Pyramid\Support\PyramidStatus;

    $statusOptions = [];
    foreach (['open', 'scheduled', 'played', 'declined', 'withdrawn', 'forfeited', 'expired'] as $key) {
        $statusOptions[$key] = PyramidStatus::label($key);
    }

    $ranksOf = static function ($challenge): string {
        $a = $challenge->challenger_pos;
        $b = $challenge->opponent_pos;

        return match (true) {
            $a === null && $b === null => __('pyramid::ui.challenges.ranks_both_waiting'),
            $a === null => __('pyramid::ui.challenges.ranks_challenger_waiting', ['b' => $b]),
            $b === null => __('pyramid::ui.challenges.ranks_opponent_waiting', ['a' => $a]),
            default => __('pyramid::ui.challenges.ranks', ['a' => $a, 'b' => $b]),
        };
    };
@endphp
@include('pyramid::partials.header', [
    'ladder' => $ladder,
    'ladders' => $ladders,
    'active' => 'challenges',
    'canAdmin' => $canAdmin,
])

<x-ui.card :aria-label="__('pyramid::ui.challenges.filter_label')">
    <form method="GET" action="{{ PyramidLinks::challenges() }}" class="flex flex-wrap items-end gap-2.5">
        <x-ui.field for="pyramid-filter-q" :label="__('pyramid::ui.challenges.name')" class="min-w-48 grow-2 basis-60">
            <x-ui.input type="search" name="q" id="pyramid-filter-q" :value="$search" />
        </x-ui.field>
        <x-ui.field for="pyramid-filter-status" :label="__('pyramid::ui.challenges.status')" class="min-w-40 grow basis-48">
            <x-ui.select name="status" id="pyramid-filter-status" :options="$statusOptions" :selected="$status" :placeholder="__('pyramid::ui.challenges.all_status')" />
        </x-ui.field>
        <x-ui.button type="submit" variant="dark" :block="false">{{ __('pyramid::ui.challenges.filter') }}</x-ui.button>
    </form>
</x-ui.card>

@if ($challenges->isEmpty())
    <x-ui.notice tone="neutral">{{ __('pyramid::ui.challenges.none') }}</x-ui.notice>
@else
    <x-ui.card padding="none">
        <x-ui.table :caption="__('pyramid::ui.challenges.caption')">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('pyramid::ui.challenges.col_pair') }}</th>
                    <th scope="col">{{ __('pyramid::ui.challenges.col_when') }}</th>
                    <th scope="col">{{ __('pyramid::ui.challenges.col_result') }}</th>
                    <th scope="col">{{ __('pyramid::ui.challenges.col_status') }}</th>
                    <th scope="col"><span class="sr-only">{{ __('pyramid::ui.challenges.details') }}</span></th>
                </tr>
            </x-slot:head>
            @foreach ($challenges as $challenge)
                @php
                    $challengerLabel = $labels[(int) $challenge->challenger_id] ?? __('pyramid::ui.unknown');
                    $opponentLabel = $labels[(int) $challenge->opponent_id] ?? __('pyramid::ui.unknown');

                    // A date (with time) wins; else an expired challenge shows the deadline that passed, a closed one
                    // the day it was resolved, a running one its deadline.
                    $when = match (true) {
                        (bool) $challenge->scheduled_at => PyramidFormat::dayTime($challenge->scheduled_at),
                        $challenge->status === 'expired' && $challenge->deadline => __('pyramid::ui.challenges.deadline_was', ['date' => PyramidFormat::shortDate($challenge->deadline)]),
                        in_array($challenge->status, ['played', 'declined', 'withdrawn', 'forfeited'], true) && $challenge->resolved => PyramidFormat::shortDate($challenge->resolved),
                        (bool) $challenge->deadline => __('pyramid::ui.challenges.deadline', ['date' => PyramidFormat::shortDate($challenge->deadline)]),
                        default => '–',
                    };
                @endphp
                <tr>
                    <td class="ds-cell--title">
                        <span class="flex flex-col">
                            <span class="font-bold" title="{{ __('pyramid::ui.challenges.pair', ['challenger' => $challengerLabel, 'opponent' => $opponentLabel]) }}">{{ PyramidFormat::shortName($challengerLabel) }} – {{ PyramidFormat::shortName($opponentLabel) }}</span>
                            <span class="text-body-s font-normal text-ink-2">{{ $ranksOf($challenge) }}</span>
                        </span>
                    </td>
                    <td data-label="{{ __('pyramid::ui.challenges.col_when') }}">{{ $when }}</td>
                    <td data-label="{{ __('pyramid::ui.challenges.col_result') }}" class="tabular-nums">{{ $challenge->score ?: '–' }}</td>
                    <td data-label="{{ __('pyramid::ui.challenges.col_status') }}"><x-ui.badge :tone="PyramidStatus::badgeGroup($challenge->status)">{{ PyramidStatus::label($challenge->status) }}</x-ui.badge></td>
                    <td class="text-right max-md:justify-end"><a href="{{ PyramidLinks::challenge((int) $challenge->id) }}" class="font-semibold text-clay underline">{{ __('pyramid::ui.challenges.details') }}</a></td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    @include('tournament-base::partials.pagination', [
        'paginator' => $challenges,
        'summary' => __('pyramid::ui.challenges.summary', [
            'from' => $challenges->firstItem(),
            'to' => $challenges->lastItem(),
            'total' => $challenges->total(),
        ]),
    ])
@endif

<div>
    <x-ui.button variant="secondary" :block="false" :href="PyramidLinks::ladder((int) $ladder->id)">{{ __('pyramid::ui.challenges.back') }}</x-ui.button>
</div>
@endsection
