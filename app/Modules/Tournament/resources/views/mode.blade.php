{{--
    Modus (old index/mode.phtml): format and rules explained for participants, every number from the
    format configuration. No own mockup: composed from Turnier-Regeln-Mobil (one disclosure per topic).
--}}
@extends('tournament::layout')

@section('tournament-content')
@php
    $criteria = [
        'points' => [__('tournament::mode.criteria.points'), __('tournament::mode.criteria.points_text', ['points' => (int) ($ranking['points_per_win'] ?? 2)])],
        'head_to_head' => [__('tournament::mode.criteria.head_to_head'), __('tournament::mode.criteria.head_to_head_text')],
        'set_diff' => [__('tournament::mode.criteria.set_diff'), __('tournament::mode.criteria.set_diff_text')],
        'game_diff' => [__('tournament::mode.criteria.game_diff'), __('tournament::mode.criteria.game_diff_text')],
        'lot' => [__('tournament::mode.criteria.lot'), __('tournament::mode.criteria.lot_text')],
    ];
    $ruleText = fn (string $rule): string => in_array($rule, ['all', 'group_winners', 'best_seconds', 'remainder'], true) ? __('tournament::mode.rules.'.$rule) : $rule;
@endphp

<div class="flex w-full max-w-3xl flex-col gap-3">
    <x-ui.card :title="__('tournament::mode.title')">
        <p>{{ __('tournament::mode.intro', ['count' => (int) $playerCount]) }}</p>
    </x-ui.card>

    <x-ui.disclosure :summary="__('tournament::mode.competitions')" open>
        <div class="flex flex-col gap-4">
            @foreach ($groupPhases as $entry)
                <section class="flex flex-col gap-2">
                    <h3 class="text-label uppercase text-ink-3">{{ $entry['phase']['label'] }}</h3>
                    <p>{{ __('tournament::mode.phase_line', ['players' => (int) $entry['players'], 'groups' => count($entry['groups']), 'matches' => (int) $entry['matches']]) }}</p>
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($entry['groups'] as $group)
                            <li><x-ui.badge tone="neutral">{{ __('tournament::mode.group_badge', ['group' => $group['key'], 'size' => (int) $group['size'], 'matches' => (int) $group['matches']]) }}</x-ui.badge></li>
                        @endforeach
                    </ul>
                    @foreach ($entry['purposes'] as $purpose)
                        @if ($purpose['kind'] === 'qualification')
                            <p><strong>{{ __('tournament::mode.purpose.best', ['count' => (int) $purpose['advancing']]) }}</strong> {{ __('tournament::mode.purpose.qualification', ['target' => $purpose['target']]) }}</p>
                        @elseif ($purpose['kind'] === 'seeding_all')
                            <x-ui.notice tone="good">{{ __('tournament::mode.purpose.seeding_all', ['target' => $purpose['target']]) }}</x-ui.notice>
                            <p class="text-body-s text-ink-2">{{ __('tournament::mode.purpose.seeding_all_note') }}</p>
                        @else
                            <x-ui.notice tone="good">{{ __('tournament::mode.purpose.seeding_rest', ['target' => $purpose['target']]) }}</x-ui.notice>
                        @endif
                    @endforeach
                    <p class="text-body-s text-ink-2">{{ __('tournament::mode.heads') }}</p>
                </section>
            @endforeach
        </div>
    </x-ui.disclosure>

    <x-ui.disclosure :summary="__('tournament::mode.match_title')">
        @if ($matchFormat === 'best_of_3_mtb')
            <p>{{ __('tournament::mode.match_mtb') }}</p>
            <p class="mt-2 text-body-s text-ink-2">{{ __('tournament::mode.match_mtb_note') }}</p>
        @else
            <p>{{ $matchFormat }}</p>
        @endif
    </x-ui.disclosure>

    <x-ui.disclosure :summary="__('tournament::mode.order_title')">
        <ol class="flex list-decimal flex-col gap-1 pl-5">
            @foreach ($ranking['criteria'] ?? [] as $step)
                @continue(! isset($criteria[$step]))
                <li>
                    <strong>{{ $criteria[$step][0] }}</strong><br>
                    <span class="text-body-s text-ink-2">{{ $criteria[$step][1] }}</span>
                </li>
            @endforeach
        </ol>
        @if (($ranking['tiebreak_scope'] ?? 'tied') === 'tied')
            <p class="mt-2 text-body-s text-ink-2">{{ __('tournament::mode.order_tied') }}</p>
        @endif
        <p class="mt-2 text-body-s text-ink-2">{{ __('tournament::mode.order_note') }}</p>
    </x-ui.disclosure>

    <x-ui.disclosure :summary="__('tournament::mode.advance_title')">
        <div class="flex flex-col gap-4">
            @foreach ($bracketPhases as $entry)
                <section class="flex flex-col gap-1.5">
                    <h3 class="text-label uppercase text-ink-3">{{ $entry['phase']['label'] }}</h3>
                    <p>
                        {{ __('tournament::mode.bracket_line', ['size' => (int) $entry['phase']['size'], 'rounds' => count($entry['rounds']), 'seeded' => (int) $entry['seeded']]) }}
                        @if ($entry['byes'])
                            · {{ trans_choice('tournament::mode.byes', (int) $entry['byes'], ['count' => (int) $entry['byes']]) }}
                        @endif
                    </p>
                    <p class="text-body-s">{{ implode(' · ', $entry['rounds']) }}</p>
                    @if (! empty($entry['phase']['qualify']))
                        <ul class="flex list-disc flex-col gap-0.5 pl-5 text-body-s">
                            @foreach ($entry['phase']['qualify'] as $rule)
                                <li>{{ __('tournament::mode.from', ['phase' => $rule['from']]) }} {{ $ruleText((string) $rule['rule']) }}@if (isset($rule['count'])) ({{ (int) $rule['count'] }})@endif</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>
    </x-ui.disclosure>

    <x-ui.disclosure :summary="__('tournament::mode.runner_up_title')">
        <p>{{ in_array($runnerUpMode, ['drop_last', 'points', 'per_match', 'lot'], true) ? __('tournament::mode.runner_up.'.$runnerUpMode) : $runnerUpMode }}</p>
    </x-ui.disclosure>

    <x-ui.disclosure :summary="__('tournament::mode.dates_title')">
        <p>{{ __('tournament::mode.dates_text') }}</p>
        <p class="mt-2 text-body-s text-ink-2">{{ __('tournament::mode.dates_note') }}</p>
    </x-ui.disclosure>

    <x-ui.disclosure :summary="__('tournament::mode.draw_title')">
        <p>{{ __('tournament::mode.draw_text') }}</p>
        <p class="mt-2 text-body-s text-ink-2">{{ __('tournament::mode.draw_note') }}</p>
    </x-ui.disclosure>
</div>
@endsection
