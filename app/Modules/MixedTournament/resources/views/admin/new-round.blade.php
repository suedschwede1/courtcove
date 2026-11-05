{{--
    "+ Neue Runde" (old admin/new-round.phtml and new-round-mexicano.phtml; "Runde konfigurieren" card of
    Admin-Mixed-Runde): participants (active preselected, paused marked), Americano also group size mode and
    distribution as option tiles, then "Vorschlag generieren" (round and proposal in one step). Mexicano forms
    groups from the standings: fixed 4_only/random, and no form at all while a round is still open.
    Below: the event's rounds.
--}}
@extends('mixed::admin.layout')

@section('title', __('mixed::common.tab_new_round').' · '.$event->title)

@section('mixed-admin-content')
@php
    $isMexicano = $event->isMexicano();
    $preselected = $participants->where('status', 'active')->count();
    $selectedText = __('mixed::admin.new_round.selected', ['count' => '__COUNT__']);
@endphp
@include('mixed::admin.partials.event-head')

@include('mixed::partials.tabs', ['event' => $event, 'active' => 'neue-runde', 'adminView' => true, 'canAdmin' => true])

<div class="flex flex-wrap items-start gap-5">
    <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-4">
        @if ($isMexicano && $openRound !== null)
            <x-ui.notice tone="attention" :title="__('mixed::admin.new_round.open_title', ['number' => (int) $openRound->round_no])">
                <p class="text-body-s">{{ __('mixed::admin.new_round.open_text') }}</p>
                <div class="mt-3">
                    <x-ui.button :block="false" :href="route('mixed.admin.round', ['round' => (int) $openRound->id])">{{ __('mixed::admin.new_round.open_link', ['number' => (int) $openRound->round_no]) }}</x-ui.button>
                </div>
            </x-ui.notice>
        @else
            <x-ui.card :title="$isMexicano ? __('mixed::admin.new_round.mexicano_title') : __('mixed::admin.new_round.title')">
                <form method="POST" action="{{ route('mixed.admin.create-round', ['event' => (int) $event->id]) }}" class="flex flex-col gap-5"
                      x-data="{ count: {{ $preselected }}, text: @js($selectedText) }"
                      x-on:change="count = $el.querySelectorAll('input[name=&quot;participants[]&quot;]:checked').length">
                    @csrf
                    <p class="text-body-s text-ink-2">{{ $isMexicano ? __('mixed::admin.new_round.mexicano_intro') : __('mixed::admin.new_round.intro') }}</p>

                    @if ($isMexicano)
                        <input type="hidden" name="group_size_mode" value="4_only">
                        <input type="hidden" name="distribution" value="random">
                    @else
                        <div class="flex flex-col gap-2">
                            <x-ui.option-group :legend="__('mixed::admin.new_round.group_size')" class="[&_.ds-options\_\_grid]:grid-cols-1 md:[&_.ds-options\_\_grid]:grid-cols-2">
                                <x-ui.option-tile name="group_size_mode" value="4_and_5" :label="__('mixed::admin.new_round.size_4_and_5')" :checked="true" />
                                <x-ui.option-tile name="group_size_mode" value="4_only" :label="__('mixed::admin.new_round.size_4_only')" />
                            </x-ui.option-group>
                            <p class="text-body-s text-ink-2">{{ __('mixed::admin.new_round.size_hint') }}</p>
                        </div>
                        <div class="flex flex-col gap-2">
                            <x-ui.option-group :legend="__('mixed::admin.new_round.distribution')" class="[&_.ds-options\_\_grid]:grid-cols-1 md:[&_.ds-options\_\_grid]:grid-cols-2">
                                @foreach (['random', 'itn', 'itn_balanced', 'age', 'ladies_apart'] as $distribution)
                                    <x-ui.option-tile name="distribution" :value="$distribution" :label="__('mixed::admin.new_round.distributions.'.$distribution)" :checked="$distribution === 'random'" />
                                @endforeach
                            </x-ui.option-group>
                            <p class="text-body-s text-ink-2">{{ __('mixed::admin.new_round.distribution_hint') }}</p>
                        </div>
                    @endif

                    @if ($participants->isEmpty())
                        <x-ui.notice tone="neutral">{{ __('mixed::admin.participants.none') }}</x-ui.notice>
                    @else
                        <x-ui.option-group :legend="$isMexicano ? __('mixed::admin.new_round.mexicano_participants') : __('mixed::admin.new_round.participants')" class="md:[&_.ds-options\_\_grid]:grid-cols-3">
                            @foreach ($participants as $participant)
                                <x-ui.option-tile type="checkbox" name="participants[]" :value="(int) $participant->id"
                                    :label="$participant->status === 'paused' ? __('mixed::admin.new_round.paused_option', ['name' => $participant->label]) : $participant->label"
                                    :checked="$participant->status === 'active'" />
                            @endforeach
                        </x-ui.option-group>
                    @endif

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-body-s text-ink-2" aria-live="polite" x-text="text.replace('__COUNT__', count)">{{ __('mixed::admin.new_round.selected', ['count' => $preselected]) }}</p>
                        <x-ui.button type="submit" :block="false">{{ $isMexicano ? __('mixed::admin.new_round.mexicano_submit') : __('mixed::admin.new_round.submit') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @endif
    </div>

    <x-ui.card :title="__('mixed::admin.new_round.rounds_title')" class="flex-[1_1_300px]">
        @if ($rounds->isEmpty())
            <p class="text-body-s text-ink-2">{{ __('mixed::admin.new_round.no_rounds') }}</p>
        @else
            <ul class="flex flex-col">
                @foreach ($rounds as $item)
                    <li class="flex items-center justify-between gap-3 border-t border-line-soft py-1.5">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-ink">{{ __('mixed::common.tab_round', ['number' => (int) $item->round_no]) }}</span>
                            <x-ui.badge :tone="$item->isCompleted() ? 'good' : ($item->isConfirmed() ? 'attention' : 'neutral')">{{ __('mixed::common.round_status.'.$item->status) }}</x-ui.badge>
                        </span>
                        <a href="{{ route('mixed.admin.round', ['round' => (int) $item->id]) }}" class="inline-flex min-h-touch-min items-center gap-1 font-semibold text-clay underline hover:text-clay-hover" aria-label="{{ __('mixed::admin.new_round.open_label', ['number' => (int) $item->round_no]) }}">{{ __('mixed::admin.new_round.open') }}<x-ui.icon name="chevron-right" :size="16" /></a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>
@endsection
