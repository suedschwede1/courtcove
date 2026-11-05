@extends('pyramid::admin.layout')

@section('title', __('pyramid::admin.index.title'))

@section('pyramid-admin-content')
@php
    use App\Modules\Pyramid\Support\PyramidFormat;
    use App\Modules\Pyramid\Support\PyramidLinks;
    use App\Modules\Pyramid\Support\PyramidStatus;
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
    <div class="flex flex-col gap-1.5">
        <p class="text-label uppercase text-ink-3">{{ __('pyramid::admin.index.eyebrow') }}</p>
        <h1 class="font-display text-display-l text-ink md:text-display-xl">{{ __('pyramid::admin.index.title') }}</h1>
        <p class="text-ink-2">{{ __('pyramid::admin.index.intro') }}</p>
    </div>
    <x-ui.button :block="false" href="#pyramid-admin-new">{{ __('pyramid::admin.index.new') }}</x-ui.button>
</div>

@if ($overdue->isNotEmpty())
    <section class="flex flex-col gap-2.5" aria-labelledby="pyramid-admin-todo">
        <h2 id="pyramid-admin-todo" class="text-label uppercase text-ink-3">{{ __('pyramid::admin.index.todo') }}</h2>
        @foreach ($overdue as $challenge)
            <x-ui.card class="border-l-4 border-l-clay" padding="md">
                <a href="{{ PyramidLinks::challenge((int) $challenge->id) }}" class="flex flex-col gap-0.5 no-underline">
                    <span class="font-semibold text-ink">{{ __('pyramid::admin.index.todo_overdue') }}</span>
                    <span class="text-body-s text-ink-2">{{ __('pyramid::admin.index.todo_sub', [
                        'ladder' => $challenge->ladder?->title ?? __('pyramid::ui.unknown'),
                        'challenger' => $challenge->challenger?->label ?? __('pyramid::ui.unknown'),
                        'opponent' => $challenge->opponent?->label ?? __('pyramid::ui.unknown'),
                        'date' => PyramidFormat::date($challenge->deadline),
                    ]) }}</span>
                </a>
            </x-ui.card>
        @endforeach
    </section>
@endif

<section class="flex flex-col gap-4" aria-labelledby="pyramid-admin-ladders">
    <h2 id="pyramid-admin-ladders" class="sr-only">{{ __('pyramid::admin.index.ladders') }}</h2>

    @if ($bySeason === [])
        <x-ui.notice tone="neutral">{{ __('pyramid::admin.index.none') }}</x-ui.notice>
    @endif

    @foreach ($bySeason as $season => $entries)
        <div class="flex flex-col gap-2.5">
            <h3 class="text-label uppercase text-ink-3">{{ __('pyramid::admin.index.season', ['season' => $season]) }}</h3>
            <div class="grid gap-2.5 md:grid-cols-2">
                @foreach ($entries as $entry)
                    @php
                        $ladder = $entry['ladder'];
                        $status = in_array($ladder->status, ['setup', 'running', 'closed'], true) ? PyramidStatus::label($ladder->status) : $ladder->status;
                    @endphp
                    <x-ui.card>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex min-w-0 flex-col gap-1">
                                <p class="flex flex-wrap items-center gap-2 font-semibold text-ink">
                                    <span>{{ $ladder->title }}</span>
                                    @if ($ladder->is_default)
                                        <x-ui.badge tone="good">{{ __('pyramid::admin.index.default') }}</x-ui.badge>
                                    @endif
                                </p>
                                <p class="text-body-s text-ink-2">{{ __('pyramid::admin.index.facts', [
                                    'category' => $ladder->category ?? __('pyramid::admin.index.no_category'),
                                    'status' => $status,
                                    'count' => $entry['count'],
                                ]) }}</p>
                            </div>
                            <x-ui.button variant="secondary" :block="false" :href="PyramidLinks::adminLadder((int) $ladder->id)">{{ __('pyramid::admin.index.edit') }}</x-ui.button>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        </div>
    @endforeach
</section>

<x-ui.card id="pyramid-admin-new" :title="__('pyramid::admin.index.create_title')">
    <form method="POST" action="{{ PyramidLinks::adminCreate() }}" class="flex flex-col gap-4">
        @csrf
        <div class="grid gap-3 md:grid-cols-[2fr_1fr_1fr]">
            <x-ui.field for="pyramid-admin-title" :label="__('pyramid::admin.index.field_title')">
                <x-ui.input name="title" id="pyramid-admin-title" required maxlength="160" :placeholder="__('pyramid::admin.index.placeholder_title', ['year' => $year])" />
            </x-ui.field>
            <x-ui.field for="pyramid-admin-season" :label="__('pyramid::admin.index.field_season')">
                <x-ui.input type="number" name="season" id="pyramid-admin-season" :value="$year" min="2000" max="2100" />
            </x-ui.field>
            <x-ui.field for="pyramid-admin-category" :label="__('pyramid::admin.index.field_category')">
                <x-ui.input name="category" id="pyramid-admin-category" list="pyramid-admin-categories" maxlength="40" :placeholder="__('pyramid::admin.index.placeholder_category')" />
                <datalist id="pyramid-admin-categories">
                    @foreach (__('pyramid::admin.index.categories') as $category)
                        <option value="{{ $category }}"></option>
                    @endforeach
                </datalist>
            </x-ui.field>
        </div>
        <div>
            <x-ui.button type="submit" :block="false">{{ __('pyramid::admin.index.create') }}</x-ui.button>
        </div>
    </form>
    <p class="mt-4 text-body-s text-ink-3">{{ __('pyramid::admin.index.create_hint') }}</p>
</x-ui.card>
@endsection
