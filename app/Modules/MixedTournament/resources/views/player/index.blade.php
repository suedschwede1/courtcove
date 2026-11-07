{{-- Member event list (mixed.events, and mixed.index while no default event is set): read-only, the default event first.
     No own mockup: Turnier-Mixed header + list cards. --}}
@extends('mixed::layout')

@section('title', __('mixed::common.list_title'))

@section('mixed-content')
@php
    use App\Modules\MixedTournament\Support\MixedFormat;

    $statusTone = ['draft' => 'neutral', 'running' => 'good', 'done' => 'neutral'];
@endphp
<div class="flex flex-wrap items-center justify-between gap-3">
    <x-ui.page-header :title="__('mixed::common.list_title')" :intro="__('mixed::common.list_intro')" />
    <div class="flex flex-wrap gap-2.5">
        <x-ui.button variant="secondary" :block="false" :href="route('mixed.info')">{{ __('mixed::common.how_it_works') }}</x-ui.button>
        @if ($isGlobalAdmin)
            <x-ui.button :block="false" :href="route('mixed.admin.index')">{{ __('mixed::common.admin_link') }}</x-ui.button>
        @endif
    </div>
</div>

@if ($events->isEmpty())
    <x-ui.notice tone="neutral">{{ __('mixed::common.no_events') }}</x-ui.notice>
@else
    <ul class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,22rem),1fr))] gap-3.5">
        @foreach ($events as $event)
            <li>
                <x-ui.card as="article" class="h-full">
                    <div class="flex flex-col gap-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <h2 class="font-display text-display-m text-ink">{{ $event->title }}</h2>
                            <div class="flex flex-wrap gap-1.5">
                                @if (isset($statusTone[$event->status]))
                                    <x-ui.badge :tone="$statusTone[$event->status]">{{ __('mixed::common.status.'.$event->status) }}</x-ui.badge>
                                @endif
                                @if ($event->is_test)
                                    <x-ui.badge tone="attention">{{ __('mixed::common.test_mode_badge') }}</x-ui.badge>
                                @endif
                                @if ($event->is_default)
                                    <x-ui.badge tone="neutral">{{ __('mixed::common.default_badge') }}</x-ui.badge>
                                @endif
                            </div>
                        </div>
                        <p class="text-body text-ink-2">{{ implode(' · ', [
                            MixedFormat::date($event->event_date),
                            trans_choice('mixed::common.meta_participants', (int) $event->participants_count, ['count' => (int) $event->participants_count]),
                            trans_choice('mixed::common.meta_rounds', (int) $event->rounds_count, ['count' => (int) $event->rounds_count]),
                            MixedFormat::mode((string) $event->mode),
                        ]) }}</p>
                        <div>
                            <x-ui.button variant="secondary" :block="false" :href="route('mixed.event', ['event' => (int) $event->id])">{{ __('mixed::common.open') }}</x-ui.button>
                        </div>
                    </div>
                </x-ui.card>
            </li>
        @endforeach
    </ul>
@endif
@endsection
