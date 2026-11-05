@extends('pyramid::layout')

@section('title', __('pyramid::rules.title'))

@section('pyramid-content')
@php
    // Every number comes from the ladder's own settings, so the page never contradicts the rule engine.
    $replace = [
        'days_to_play' => (int) $ladder->days_to_play,
        'loser_cooldown_days' => (int) $ladder->loser_cooldown_days,
        'winner_protected_days' => (int) $ladder->winner_protected_days,
    ];

    $sections = [];
    foreach (['right', 'mode', 'flow', 'after', 'decline', 'entry', 'fair'] as $key) {
        $section = ['id' => 'pyramid-rule-'.$key, 'title' => __('pyramid::rules.'.$key.'.title'), 'text' => null, 'items' => []];

        if (in_array($key, ['flow', 'after'], true)) {
            foreach ([1, 2, 3, 4] as $n) {
                $section['items'][] = __('pyramid::rules.'.$key.'.item_'.$n, $replace);
            }
        } else {
            $section['text'] = __('pyramid::rules.'.$key.'.text');
        }

        $sections[] = $section;
    }
@endphp
@include('pyramid::partials.header', [
    'ladder' => $ladder,
    'ladders' => $ladders,
    'active' => 'rules',
    'canAdmin' => $canAdmin,
])

{{-- Wide screens: table of contents beside one article. Phones: one disclosure per rule. --}}
<div class="hidden items-start gap-7 md:flex">
    <nav aria-label="{{ __('pyramid::rules.toc') }}" class="flex w-64 shrink-0 flex-col gap-0.5 rounded-xl border border-line bg-surface p-3.5">
        <span class="px-2 pb-1.5 text-body-s font-bold uppercase tracking-wider text-ink-3">{{ __('pyramid::rules.toc') }}</span>
        @foreach ($sections as $section)
            <a href="#{{ $section['id'] }}" class="flex min-h-10 items-center rounded-lg px-2 font-semibold text-ink no-underline hover:bg-ground">{{ $section['title'] }}</a>
        @endforeach
    </nav>

    <x-ui.card as="article" padding="lg" class="min-w-0 flex-1">
        <div class="flex flex-col gap-5 leading-relaxed">
            <h2 class="font-display text-display-l text-ink">{{ __('pyramid::rules.title') }}</h2>
            @foreach ($sections as $section)
                <section id="{{ $section['id'] }}" class="flex flex-col gap-1.5">
                    <h3 class="font-display text-display-m text-ink">{{ $section['title'] }}</h3>
                    @if ($section['text'] !== null)
                        <p class="max-w-prose text-ink-2">{{ $section['text'] }}</p>
                    @else
                        <ul class="flex max-w-prose list-disc flex-col gap-1 pl-5 text-ink-2">
                            @foreach ($section['items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>
    </x-ui.card>
</div>

<div class="flex flex-col gap-2 md:hidden">
    @foreach ($sections as $section)
        <x-ui.disclosure :summary="$section['title']">
            @if ($section['text'] !== null)
                <p class="text-ink-2">{{ $section['text'] }}</p>
            @else
                <ul class="flex list-disc flex-col gap-1 pl-5 text-ink-2">
                    @foreach ($section['items'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif
        </x-ui.disclosure>
    @endforeach
</div>
@endsection
