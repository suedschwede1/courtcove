{{--
    Mixed overview for every logged-in user (old admin/index.phtml), layout from Admin-Turniere-Mobil:
    "Zu erledigen" (proposed rounds of events the viewer administers), "Bewerbe" (event cards; "Öffnen"
    leads event admins to the admin view and everyone else to the player view), and for global admins the
    import link and the form for a new event.
--}}
@extends('mixed::admin.layout')

@section('title', __('mixed::admin.index.title'))

@section('mixed-admin-content')
@php
    use App\Modules\MixedTournament\Support\MixedFormat;
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
    <div class="flex flex-col gap-1.5">
        <p class="text-label uppercase text-ink-3">{{ __('mixed::admin.eyebrow') }}</p>
        <h1 class="font-display text-display-l text-ink md:text-display-xl">{{ __('mixed::admin.index.title') }}</h1>
        <p class="text-ink-2">{{ __('mixed::admin.index.intro') }}</p>
    </div>
    <div class="flex flex-wrap gap-2.5">
        @if ($isGlobalAdmin)
            <x-ui.button variant="secondary" :block="false" :href="route('mixed.admin.import-members')">{{ __('mixed::admin.index.import_link') }}</x-ui.button>
        @endif
        <x-ui.button variant="secondary" :block="false" :href="route('mixed.info')">{{ __('mixed::common.how_it_works') }}</x-ui.button>
    </div>
</div>

@if ($todos->isNotEmpty())
    <section class="flex flex-col gap-2.5" aria-labelledby="mixed-admin-todo">
        <h2 id="mixed-admin-todo" class="text-label uppercase text-ink-3">{{ __('mixed::admin.index.todo') }}</h2>
        @foreach ($todos as $round)
            <x-ui.card as="div" class="border-l-4 border-l-court">
                <a href="{{ route('mixed.admin.round', ['round' => (int) $round->id]) }}" class="flex items-center justify-between gap-3 text-ink no-underline">
                    <span class="flex min-w-0 flex-col gap-0.5">
                        <span class="font-semibold">{{ __('mixed::admin.index.todo_round', ['number' => (int) $round->round_no]) }}</span>
                        <span class="text-body-s text-ink-2">{{ __('mixed::admin.index.todo_sub', ['title' => $round->event_title]) }}</span>
                    </span>
                    <x-ui.icon name="chevron-right" class="shrink-0 text-ink-3" />
                </a>
            </x-ui.card>
        @endforeach
    </section>
@endif

<section class="flex flex-col gap-2.5" aria-labelledby="mixed-admin-events">
    <h2 id="mixed-admin-events" class="text-label uppercase text-ink-3">{{ __('mixed::admin.index.events') }}</h2>
    @if ($events->isEmpty())
        <x-ui.notice tone="neutral">{{ __('mixed::admin.index.no_events') }}</x-ui.notice>
    @else
        <div class="grid gap-2.5 md:grid-cols-2">
            @foreach ($events as $event)
                @php
                    $facts = __('mixed::admin.index.event_facts', ['date' => MixedFormat::date($event->event_date), 'mode' => MixedFormat::mode((string) $event->mode)]);
                    if ((int) $event->rounds_max_round_no > 0) {
                        $facts .= ' · '.__('mixed::admin.index.event_round', ['number' => (int) $event->rounds_max_round_no]);
                    }
                    $target = $canAdmin[(int) $event->id]
                        ? route('mixed.admin.event', ['event' => (int) $event->id])
                        : route('mixed.event', ['event' => (int) $event->id]);
                @endphp
                <x-ui.card as="div">
                    <a href="{{ $target }}" class="flex items-center justify-between gap-3 text-ink no-underline" aria-label="{{ __('mixed::admin.index.open_event', ['title' => $event->title]) }}">
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{ $event->title }}</span>
                                @if ($event->is_default)
                                    <x-ui.badge tone="good">{{ __('mixed::common.default_badge') }}</x-ui.badge>
                                @endif
                                @if ($event->is_test)
                                    <x-ui.badge tone="attention">{{ __('mixed::common.test_mode_badge') }}</x-ui.badge>
                                @endif
                            </span>
                            <span class="text-body-s text-ink-2">{{ $facts }}</span>
                        </span>
                        <x-ui.icon name="chevron-right" class="shrink-0 text-ink-3" />
                    </a>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</section>

@if ($isGlobalAdmin)
    <x-ui.card id="mixed-admin-new" :title="__('mixed::admin.index.create_title')">
        <form method="POST" action="{{ route('mixed.admin.create-event') }}" class="flex flex-col gap-4">
            @csrf
            <div class="grid gap-3 md:grid-cols-[2fr_1fr]">
                <x-ui.field for="mixed-new-title" :label="__('mixed::admin.index.field_title')">
                    <x-ui.input name="title" id="mixed-new-title" required maxlength="255" :placeholder="__('mixed::admin.index.placeholder_title')" />
                </x-ui.field>
                <x-ui.field for="mixed-new-date" :label="__('mixed::admin.index.field_date')">
                    <x-ui.input type="date" name="event_date" id="mixed-new-date" required />
                </x-ui.field>
            </div>
            <x-ui.option-group :legend="__('mixed::admin.index.field_mode')">
                <x-ui.option-tile name="mode" value="americano" :label="__('mixed::common.mode_americano')" :checked="old('mode') !== 'mexicano'" />
                <x-ui.option-tile name="mode" value="mexicano" :label="__('mixed::common.mode_mexicano')" :checked="old('mode') === 'mexicano'" />
            </x-ui.option-group>
            <div>
                <x-ui.button type="submit" :block="false">{{ __('mixed::admin.index.create') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endif
@endsection
