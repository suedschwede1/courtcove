@extends('pyramid::layout')

@section('title', __('pyramid::ui.challenge.title'))

@section('pyramid-content')
@php
    use App\Modules\Pyramid\Support\PyramidLinks;

    $parties = [
        ['player' => $challenger, 'you' => true],
        ['player' => $opponent, 'you' => false],
    ];
@endphp
@include('pyramid::partials.header', [
    'ladder' => $ladder,
    'ladders' => $ladders,
    'active' => 'ranking',
    'canAdmin' => $canAdmin,
])

<div class="mx-auto flex w-full max-w-xl flex-col gap-4">
    <x-ui.card :title="__('pyramid::ui.challenge.title')" level="2">
        <form method="POST" action="{{ route('pyramide.challenge.store', ['playerId' => (int) $opponent->id]) }}" class="flex flex-col gap-4">
            @csrf

            <ul class="flex flex-col gap-2">
                @foreach ($parties as $party)
                    <li class="flex items-center gap-3 rounded-lg bg-ground p-3">
                        <x-ui.avatar :name="$party['player']->label" />
                        <span class="flex min-w-0 flex-col">
                            <strong class="break-words">{{ $party['player']->label }}@if ($party['you']) · {{ __('pyramid::ui.you') }}@endif</strong>
                            @if ($party['player']->position !== null)
                                <span class="text-body-s text-ink-2">{{ __('pyramid::ui.player.rank', ['n' => (int) $party['player']->position]) }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>

            <p class="text-ink-2">{{ __('pyramid::ui.challenge.info', ['days' => (int) $ladder->days_to_play]) }}</p>

            <x-ui.field for="pyramid-scheduled-at" :label="__('pyramid::ui.challenge.scheduled_at')" :help="__('pyramid::ui.challenge.scheduled_at_help')">
                <x-ui.input type="datetime-local" name="scheduled_at" id="pyramid-scheduled-at" />
            </x-ui.field>

            <div class="flex flex-col gap-2">
                <x-ui.button type="submit">{{ __('pyramid::ui.challenge.submit') }}</x-ui.button>
                <x-ui.button variant="secondary" :href="PyramidLinks::ladder((int) $ladder->id)">{{ __('pyramid::ui.challenge.cancel') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
@endsection
