@extends('pyramid::layout')

@section('title', __('pyramid::ui.not_found.title').' · '.__('pyramid::ui.module'))

{{--
    Friendly 404 of the public Pyramid pages (rendered by PyramidNotFound, status 404).
    Needs: $message (the old abort text), $hasLadder (false: no ladder at all, the way back leads to the calendar).
--}}
@section('pyramid-content')
<div class="mx-auto flex w-full max-w-2xl flex-col gap-4 md:py-6">
    <x-ui.card>
        <div class="flex flex-col items-start gap-3">
            <p class="text-label uppercase text-ink-3">{{ __('pyramid::ui.module') }}</p>
            <h1 class="font-display text-display-l text-ink">{{ __('pyramid::ui.not_found.title') }}</h1>
            <p class="font-semibold text-ink">{{ $message }}</p>
            @if ($hasLadder)
                <p class="text-ink-2">{{ __('pyramid::ui.not_found.hint') }}</p>
            @endif
            <div class="flex flex-wrap gap-2.5">
                @if ($hasLadder)
                    <x-ui.button :block="false" :href="route('pyramide.index')">{{ __('pyramid::ui.not_found.to_ladder') }}</x-ui.button>
                    <x-ui.button variant="secondary" :block="false" :href="route('calendar.index')">{{ __('pyramid::ui.not_found.to_calendar') }}</x-ui.button>
                @else
                    <x-ui.button :block="false" :href="route('calendar.index')">{{ __('pyramid::ui.not_found.to_calendar') }}</x-ui.button>
                @endif
            </div>
        </div>
    </x-ui.card>
</div>
@endsection
