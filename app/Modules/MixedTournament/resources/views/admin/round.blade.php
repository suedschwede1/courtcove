{{--
    Americano round workspace (old admin/round.phtml; Admin-Mixed-Runde, Admin-Mixed-Runde-Mobil, and for score
    entry Turnier-Mixed-Mobil).
    proposed: "Runde konfigurieren" (group size, distribution, participants, Neu generieren), Prüfung des
    Vorschlags, group cards with the swap picker, Runde löschen / Bestätigen. Pairings stay hidden until the
    round is confirmed (ZF2 rule: only the grouping is open, and it changes with every swap).
    confirmed/completed: group progress, match cards with the five result buttons (score users and admins,
    only while confirmed; one-shot scroll restore), Runde abschließen / Runde wieder öffnen (admins), top 5.
    Data: RoundViewData::build() with preview, plus $participants and $roundParticipantIds.
--}}
@extends('mixed::admin.layout')

@section('title', __('mixed::common.tab_round', ['number' => (int) $round->round_no]).' · '.$event->title)

@section('mixed-admin-content')
@php
    use App\Modules\MixedTournament\Support\MixedFormat;

    $unknown = __('mixed::common.unknown_player');
    $names = static fn (array $ids): string => implode(', ', array_map(static fn (int $id): string => $labels[$id] ?? $unknown, $ids));
    $isProposed = $round->isProposed();
    $isConfirmed = $round->isConfirmed();
    $withResults = $isConfirmed && $canScore;
    $context = static function (int $groupNo, bool $extra) use ($waves, $waveCount): string {
        $parts = [__('mixed::common.group', ['number' => $groupNo + 1])];
        if ($extra) {
            $parts[] = __('mixed::common.extra_group');
        }
        if ($waveCount > 1 && isset($waves[$groupNo])) {
            $parts[] = __('mixed::common.wave', ['number' => $waves[$groupNo]]);
        }

        return implode(' · ', $parts);
    };
    $selectedText = __('mixed::admin.new_round.selected', ['count' => '__COUNT__']);
@endphp
@component('mixed::admin.partials.round-head', ['event' => $event, 'round' => $round, 'canAdmin' => $canAdmin])
    @slot('actions')
        @if ($byGroup !== [] && $isConfirmed)
            <x-ui.button variant="secondary" :block="false" :href="route('mixed.scorecard', ['round' => (int) $round->id])" target="_blank" rel="noopener">{{ __('mixed::common.print_scorecard') }}</x-ui.button>
        @endif
    @endslot
@endcomponent

@include('mixed::partials.tabs', ['event' => $event, 'active' => 'round:'.$round->id, 'adminView' => true, 'canAdmin' => $canAdmin])

@if ($withResults)
    @include('mixed::partials.scroll-restore', ['scrollId' => $round->id])
@endif

@if ($byGroup === [])
    @if ($isProposed && $canAdmin)
        {{-- A proposed round without proposal (only rounds from before the atomic create): choose and generate. --}}
        <x-ui.card :title="__('mixed::admin.round.select_title')">
            <form method="POST" action="{{ route('mixed.admin.generate', ['round' => (int) $round->id]) }}" class="flex flex-col gap-4">
                @csrf
                <p class="text-body-s text-ink-2">{{ __('mixed::admin.round.select_hint') }}</p>
                <x-ui.option-group :legend="__('mixed::admin.new_round.participants')" class="md:[&_.ds-options\_\_grid]:grid-cols-3">
                    @foreach ($participants as $participant)
                        <x-ui.option-tile type="checkbox" name="participants[]" :value="(int) $participant->id"
                            :label="$participant->status === 'paused' ? __('mixed::admin.new_round.paused_option', ['name' => $participant->label]) : $participant->label"
                            :checked="$participant->status === 'active'" />
                    @endforeach
                </x-ui.option-group>
                <div>
                    <x-ui.button type="submit" :block="false">{{ __('mixed::admin.round.generate_groups') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @else
        <x-ui.notice tone="neutral">{{ $isProposed ? __('mixed::admin.round.not_generated') : __('mixed::common.no_proposal') }}</x-ui.notice>
    @endif
@elseif ($isProposed)
    <div @class([
        'grid items-start gap-5',
        'xl:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] xl:grid-rows-[auto_auto_auto_1fr]' => $canAdmin,
    ])>
        <div class="flex min-w-0 flex-col gap-3.5 xl:col-start-2 xl:row-start-1">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.badge tone="neutral">{{ __('mixed::common.distribution_label', ['label' => MixedFormat::distribution((string) $round->distribution)]) }}</x-ui.badge>
            </div>
            @include('mixed::partials.round-preview')
        </div>

        <div class="flex min-w-0 flex-col gap-3.5 xl:col-start-2 xl:row-start-2">
            @if ($playsTwice !== [])
                <x-ui.notice tone="attention" :title="__('mixed::common.plays_twice_title')">
                    <p>{{ $names($playsTwice) }}</p>
                    <p class="text-body-s">{{ __('mixed::common.plays_twice_hint') }}</p>
                </x-ui.notice>
            @endif
            <p class="text-body-s text-ink-2">{{ __('mixed::common.proposed_hint') }}</p>
            @include('mixed::partials.swap-picker', ['canSwap' => $canAdmin])
        </div>

        @if ($canAdmin)
            <x-ui.card class="min-w-0 xl:col-start-2 xl:row-start-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <form method="POST" action="{{ route('mixed.admin.delete-round', ['round' => (int) $round->id]) }}" x-data x-on:submit="if (! confirm(@js(__('mixed::admin.round.delete_confirm')))) $event.preventDefault()">
                        @csrf
                        <x-ui.button type="submit" variant="danger" :block="false">{{ __('mixed::admin.round.delete') }}</x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('mixed.admin.confirm', ['round' => (int) $round->id]) }}">
                        @csrf
                        <x-ui.button type="submit" :block="false">{{ __('mixed::admin.round.confirm') }}</x-ui.button>
                    </form>
                </div>
            </x-ui.card>

            {{-- Configuration: left column on wide screens, after the groups on phones. --}}
            <x-ui.card :title="__('mixed::admin.round.config_title')" class="min-w-0 xl:col-start-1 xl:row-span-4 xl:row-start-1">
                <form method="POST" action="{{ route('mixed.admin.generate', ['round' => (int) $round->id]) }}" class="flex flex-col gap-4"
                      x-data="{ count: {{ count($roundParticipantIds) }}, text: @js($selectedText), changing: false }"
                      x-on:change="count = $el.querySelectorAll('input[name=&quot;participants[]&quot;]:checked').length">
                    @csrf
                    <x-ui.option-group :legend="__('mixed::admin.new_round.group_size')" class="[&_.ds-options\_\_grid]:grid-cols-1">
                        <x-ui.option-tile name="group_size_mode" value="4_and_5" :label="__('mixed::admin.new_round.size_4_and_5')" :checked="$round->group_size_mode === '4_and_5'" />
                        <x-ui.option-tile name="group_size_mode" value="4_only" :label="__('mixed::admin.new_round.size_4_only')" :checked="$round->group_size_mode === '4_only'" />
                    </x-ui.option-group>
                    <x-ui.option-group :legend="__('mixed::admin.new_round.distribution')" class="[&_.ds-options\_\_grid]:grid-cols-1">
                        @foreach (['random', 'itn', 'itn_balanced', 'age', 'ladies_apart'] as $distribution)
                            <x-ui.option-tile name="distribution" :value="$distribution" :label="__('mixed::admin.new_round.distributions.'.$distribution)" :checked="$round->distribution === $distribution" />
                        @endforeach
                    </x-ui.option-group>

                    <div class="flex flex-wrap items-center gap-x-1 text-body-s text-ink-2">
                        <span aria-live="polite" x-text="text.replace('__COUNT__', count)">{{ __('mixed::admin.new_round.selected', ['count' => count($roundParticipantIds)]) }}</span>
                        <span aria-hidden="true">·</span>
                        <x-ui.button variant="text" :block="false" class="px-1 text-clay underline" x-on:click="changing = ! changing" x-bind:aria-expanded="changing ? 'true' : 'false'" aria-controls="mixed-round-participants" aria-label="{{ __('mixed::admin.round.change_label') }}">{{ __('mixed::admin.round.change') }}</x-ui.button>
                    </div>
                    {{-- Always submitted (hidden or not): the round keeps its participants unless changed here. --}}
                    <div id="mixed-round-participants" x-show="changing" x-cloak>
                        <x-ui.option-group :legend="__('mixed::admin.new_round.participants')" class="[&_.ds-options\_\_grid]:grid-cols-1">
                            @foreach ($participants as $participant)
                                <x-ui.option-tile type="checkbox" name="participants[]" :value="(int) $participant->id"
                                    :label="$participant->status === 'paused' ? __('mixed::admin.new_round.paused_option', ['name' => $participant->label]) : $participant->label"
                                    :checked="in_array((int) $participant->id, $roundParticipantIds, true)" />
                            @endforeach
                        </x-ui.option-group>
                    </div>
                    <x-ui.button type="submit" variant="dark">{{ __('mixed::admin.round.regenerate') }}</x-ui.button>
                </form>
            </x-ui.card>
        @endif
    </div>
@else
    @if ($playsTwice !== [])
        <x-ui.notice tone="attention" :title="__('mixed::common.plays_twice_title')">
            <p>{{ $names($playsTwice) }}</p>
            <p class="text-body-s">{{ __('mixed::common.plays_twice_hint') }}</p>
        </x-ui.notice>
    @endif

    <div class="flex flex-wrap items-start gap-5">
        <div class="flex min-w-0 flex-[999_1_640px] flex-col gap-3.5">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.badge tone="neutral">{{ __('mixed::common.distribution_label', ['label' => MixedFormat::distribution((string) $round->distribution)]) }}</x-ui.badge>
                @foreach ($byGroup as $groupNo => $matches)
                    @php
                        $total = count($matches);
                        $done = count(array_filter($matches, static fn ($m): bool => $m->games_a !== null));
                    @endphp
                    <x-ui.badge :tone="$done === $total ? 'good' : 'attention'">{{ __('mixed::common.group', ['number' => $groupNo + 1]) }} · {{ $done === $total ? __('mixed::common.group_done') : $done.'/'.$total }}</x-ui.badge>
                @endforeach
            </div>

            @if ($waveCount > 1)
                <p class="text-body-s text-ink-2">{{ __('mixed::common.waves_hint', ['courts' => $courtCount, 'waves' => $waveCount]) }}</p>
            @endif

            <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,17rem),1fr))] gap-3.5">
                @foreach ($byGroup as $groupNo => $matches)
                    @foreach ($matches as $match)
                        @include('mixed::partials.match-card', ['match' => $match, 'labels' => $labels, 'context' => $context($groupNo, (bool) $match->is_extra), 'withResults' => $withResults])
                    @endforeach
                @endforeach
            </div>

            <x-ui.card>
                <div class="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center md:justify-between">
                    @if ($canAdmin && $isConfirmed)
                        <form method="POST" action="{{ route('mixed.admin.complete', ['round' => (int) $round->id]) }}" x-data x-on:submit="if (! confirm(@js(__('mixed::admin.round.complete_confirm')))) $event.preventDefault()">
                            @csrf
                            <x-ui.button type="submit" variant="dark">{{ __('mixed::admin.round.complete') }}</x-ui.button>
                        </form>
                    @elseif ($canAdmin && $round->isCompleted())
                        <form method="POST" action="{{ route('mixed.admin.reopen', ['round' => (int) $round->id]) }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary">{{ __('mixed::admin.round.reopen') }}</x-ui.button>
                        </form>
                    @endif
                    <x-ui.button variant="text" :href="route('mixed.round', ['round' => (int) $round->id])">{{ __('mixed::admin.round.overview') }}</x-ui.button>
                </div>
            </x-ui.card>
        </div>

        @include('mixed::partials.top5', ['top5' => $top5, 'labels' => $labels, 'event' => $event, 'standingsUrl' => $canAdmin ? route('mixed.admin.standings', ['event' => (int) $event->id]) : route('mixed.standings', ['event' => (int) $event->id])])
    </div>
@endif
@endsection
