@extends('pyramid::layout')

@section('title', $player->label)

@section('pyramid-content')
@php
    use App\Modules\Pyramid\Support\PyramidFormat;
    use App\Modules\Pyramid\Support\PyramidLinks;
    use App\Support\Ui\Initials;

    $standing = $player->position === null
        ? __('pyramid::ui.player.no_rank')
        : __('pyramid::ui.player.rank', ['n' => (int) $player->position]);
    $rating = $player->itn === null
        ? __('pyramid::ui.player.itn_none', ['rated' => PyramidFormat::itn($player->itn_rated)])
        : __('pyramid::ui.player.itn', ['itn' => PyramidFormat::itn($player->itn)]);
    $detailLine = $standing.' · '.$rating;

    // Only the administration sees a phone number: this page has no challenge context.
    if ($canAdmin && filled($player->phone)) {
        $detailLine .= ' · '.$player->phone;
    }

    $canChallenge = $challengeVerdict === null;
    $isBlocked = $challengeVerdict !== null && $challengeVerdict !== false;
    $hint = null;

    if ($canChallenge) {
        $hint = $viewer->position === null
            ? __('pyramid::ui.player.hint_entering', ['name' => $player->label])
            : __('pyramid::ui.player.hint_ok', ['own' => (int) $viewer->position, 'other' => (int) $player->position]);
    }
@endphp
@include('pyramid::partials.page-bar', ['ladder' => $ladder, 'title' => __('pyramid::ui.player.title')])

<div class="mx-auto flex w-full max-w-2xl flex-col gap-4">
    <x-ui.card :aria-label="__('pyramid::ui.player.title')">
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <x-ui.avatar :name="$player->label" class="size-14 bg-sand text-body-l text-ink" />
                <div class="flex min-w-0 flex-col">
                    <h2 class="break-words font-display text-display-l leading-none text-ink">{{ $player->label }}</h2>
                    <p class="text-ink-2">{{ $detailLine }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <x-ui.kpi :label="__('pyramid::ui.player.wins')" :value="$won" class="items-center rounded-lg bg-court-soft p-3 text-court-ink" />
                <x-ui.kpi :label="__('pyramid::ui.player.losses')" :value="$lost" class="items-center rounded-lg bg-danger-soft p-3 text-danger" />
            </div>

            @if ($canChallenge)
                <x-ui.button :href="PyramidLinks::challengeConfirm((int) $player->id)" class="bg-court text-on-court">{{ __('pyramid::ui.player.challenge') }}</x-ui.button>
                <p class="text-center text-body-s text-ink-2">{{ $hint }}</p>
            @elseif ($isBlocked)
                <x-ui.button variant="secondary" :disabled="true" class="border-line bg-sand text-ink-3 opacity-100">{{ __('pyramid::ui.player.challenge') }}</x-ui.button>
                <p class="text-center text-body-s text-ink-2">{{ $challengeVerdict->message() }}</p>
            @endif
        </div>
    </x-ui.card>

    <x-ui.card :title="__('pyramid::ui.player.history')" padding="none">
        @if ($history === [])
            <p class="p-4 text-ink-2">{{ __('pyramid::ui.player.history_none') }}</p>
        @else
            <ul class="flex flex-col">
                @foreach ($history as $match)
                    <li class="flex items-center gap-3 border-t border-line px-4 py-2.5 first:border-t-0">
                        <span class="w-24 shrink-0 text-ink-2 tabular-nums">{{ $match['date'] }}</span>
                        <span class="min-w-0 flex-1 break-words font-semibold">{{ PyramidFormat::shortName($match['opponent']) }}</span>
                        <span class="font-bold tabular-nums {{ $match['won'] ? 'text-court-ink' : 'text-danger' }}">
                            {{ $match['score'] }}
                            <span class="sr-only">({{ $match['won'] ? __('pyramid::ui.player.won') : __('pyramid::ui.player.lost') }})</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <div>
        <x-ui.button variant="secondary" :block="false" class="max-md:hidden" :href="PyramidLinks::ladder((int) $ladder->id)">{{ __('pyramid::ui.player.back') }}</x-ui.button>
    </div>
</div>
@endsection
