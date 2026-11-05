@extends('pyramid::admin.layout')

@section('title', __('pyramid::admin.ladder.title', ['title' => $ladder->title, 'season' => (int) $ladder->season]))
@section('admin-title', $ladder->title)
{{-- Phone admin bar (Admin-Pyramide-Mobil): back to the overview, the pyramid eyebrow above the ladder title. --}}
@section('admin-back', \App\Modules\Pyramid\Support\PyramidLinks::adminIndex())
@section('admin-eyebrow', __('pyramid::admin.index.eyebrow'))

{{--
    Dashboard of one ladder. Needs: $ladder, $ladders, $counts (active players per ladder id), $otherLadders,
    $ranking, $players (paginator of the filtered page), $page, $pages, $total, $search, $status, $waiting,
    $overdue, $resolved, $problems (list of strings), $labels, $suggestedDeadline.
    The member list is not part of the page: the picker in the add-player card asks the server on demand.
--}}
@section('pyramid-admin-content')
@php
    use App\Modules\Pyramid\Support\PyramidLinks;
    use App\Modules\Pyramid\Support\PyramidStatus;

    $statusLabel = static fn (string $status): string => in_array($status, ['setup', 'running', 'closed'], true)
        ? PyramidStatus::label($status)
        : $status;
@endphp
{{-- Phones: the admin bar carries eyebrow and title, so the head stays for screen readers only. --}}
<div class="flex flex-wrap items-end justify-between gap-3 max-md:sr-only">
    <div class="flex flex-col gap-1.5">
        <p class="text-label uppercase text-ink-3">{{ __('pyramid::admin.index.eyebrow') }}</p>
        <h1 class="font-display text-display-l text-ink md:text-display-xl">{{ __('pyramid::admin.ladder.title', ['title' => $ladder->title, 'season' => (int) $ladder->season]) }}</h1>
    </div>
    <div class="flex flex-wrap gap-2.5 max-md:hidden">
        <x-ui.button variant="secondary" :block="false" :href="PyramidLinks::ladder((int) $ladder->id)">{{ __('pyramid::admin.ladder.public_view') }}</x-ui.button>
        <x-ui.button :block="false" :href="PyramidLinks::adminIndex().'#pyramid-admin-new'">{{ __('pyramid::admin.ladder.new_ladder') }}</x-ui.button>
    </div>
</div>

{{-- Phones: the head above is for screen readers only, so the public view gets its own link. --}}
<x-ui.button variant="text" :block="false" class="self-start md:hidden" :href="PyramidLinks::ladder((int) $ladder->id)">{{ __('pyramid::admin.ladder.public_view') }}</x-ui.button>

@if (count($ladders) > 1)
    <div role="group" aria-label="{{ __('pyramid::admin.ladder.ladders_label') }}" class="flex flex-wrap gap-2">
        @foreach ($ladders as $chipLadder)
            <x-ui.chip :href="PyramidLinks::adminLadder((int) $chipLadder->id)" :active="(int) $chipLadder->id === (int) $ladder->id">{{ __('pyramid::admin.ladder.chip', [
                'name' => trim(($chipLadder->category === null ? '' : $chipLadder->category.' ').(int) $chipLadder->season),
                'status' => $statusLabel((string) $chipLadder->status),
                'count' => $counts[(int) $chipLadder->id] ?? 0,
            ]) }}</x-ui.chip>
        @endforeach
    </div>
@endif

@if ($problems !== [])
    @include('pyramid::admin.partials.diagnose')
@endif

@if ($overdue !== [])
    @include('pyramid::admin.partials.overdue')
@endif

<div class="flex flex-wrap items-start gap-5">
    <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-5">
        @include('pyramid::admin.partials.players')

        @if ($resolved->isNotEmpty())
            @include('pyramid::admin.partials.corrections')
        @endif
    </div>

    <div class="flex flex-[1_1_320px] flex-col gap-4">
        @include('pyramid::admin.partials.waiting')
        @include('pyramid::admin.partials.add-player')

        @if ($otherLadders->isNotEmpty())
            @include('pyramid::admin.partials.copy')
        @endif

        @include('pyramid::admin.partials.settings')

        @if ($globalAdmin)
            @include('pyramid::admin.partials.grants')
        @endif

        @include('pyramid::admin.partials.season')
    </div>
</div>

{{-- Phones: the primary action sits in a fixed bar (Admin-Pyramide-Mobil); the anchor works without scripts. --}}
<div class="ds-phone-actionbar-spacer" aria-hidden="true"></div>
<div class="ds-phone-actionbar">
    <x-ui.button href="#pyramid-admin-add" x-data x-on:click="$nextTick(() => document.getElementById('pyramid-add-picker')?.focus({ preventScroll: true }))">{{ __('pyramid::admin.ladder.add.title') }}</x-ui.button>
</div>
@endsection
