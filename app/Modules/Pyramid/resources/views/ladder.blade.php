@extends('pyramid::layout')

@section('title', $ladder->title)

@section('pyramid-content')
@php
    use App\Modules\Pyramid\Support\PyramidFormat;
    use App\Modules\Pyramid\Support\PyramidLinks;

    $month = now()->month;
@endphp
@include('pyramid::partials.header', [
    'ladder' => $ladder,
    'ladders' => $ladders,
    'active' => 'ranking',
    'canAdmin' => $canAdmin,
    'playerCount' => $ranking->count(),
    'openCount' => $openChallenges->count(),
])

@if ($ladder->status === 'setup')
    <x-ui.notice tone="neutral">{{ __('pyramid::ui.notice_setup') }}</x-ui.notice>
@elseif ($ladder->status === 'closed')
    <x-ui.notice tone="neutral">{{ __('pyramid::ui.notice_closed') }}</x-ui.notice>
@endif

{{-- Rule 2.2: championship matches in May and June come first. A hint, not a block, as in the rules. --}}
@if ($month === 5 || $month === 6)
    <x-ui.notice tone="attention">{{ __('pyramid::ui.notice_championship') }}</x-ui.notice>
@endif

@include('pyramid::partials.my-challenges', ['variant' => 'banner', 'viewer' => $viewer, 'isLoggedIn' => $isLoggedIn, 'openChallenges' => $openChallenges, 'ranking' => $ranking, 'labels' => $labels])

<div class="flex flex-col gap-5 xl:flex-row xl:items-start">
    <x-ui.card :aria-label="__('pyramid::ui.ranking')" padding="lg" class="min-w-0 xl:flex-1">
        @include('pyramid::partials.ladder-grid', ['rows' => $rows, 'blocked' => $blocked, 'viewer' => $viewer])
    </x-ui.card>

    <aside class="flex w-full flex-col gap-4 xl:w-80 xl:shrink-0">
        @include('pyramid::partials.my-challenges', ['variant' => 'card', 'viewer' => $viewer, 'isLoggedIn' => $isLoggedIn, 'openChallenges' => $openChallenges, 'ranking' => $ranking, 'labels' => $labels])

        <x-ui.card :title="__('pyramid::ui.waiting.title')">
            <div class="flex flex-col gap-3">
                @if ($waiting->isEmpty())
                    <p class="text-ink-2">{{ __('pyramid::ui.waiting.none') }}</p>
                @else
                    <ul class="flex flex-col gap-1 text-ink-2">
                        @foreach ($waiting as $player)
                            <li>{{ PyramidFormat::shortName($player->label) }} · ITN {{ PyramidFormat::itn($player->itn) }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($isLoggedIn && $viewer === null)
                    <x-ui.disclosure :summary="__('pyramid::ui.waiting.join')">
                        <form method="POST" action="{{ PyramidLinks::join() }}" class="flex flex-col gap-3">
                            @csrf
                            <input type="hidden" name="ladderId" value="{{ $ladder->id }}">
                            <p class="text-ink-2">{{ __('pyramid::ui.waiting.join_intro', ['title' => $ladder->title]) }}</p>
                            <x-ui.field for="pyramid-join-itn" :label="__('pyramid::ui.waiting.itn_label')" :help="__('pyramid::ui.waiting.itn_help')">
                                <x-ui.input name="itn" id="pyramid-join-itn" inputmode="decimal" :placeholder="PyramidFormat::itn($ladder->itn_default)" />
                            </x-ui.field>
                            <x-ui.button type="submit">{{ __('pyramid::ui.waiting.submit') }}</x-ui.button>
                        </form>
                    </x-ui.disclosure>
                @endif
            </div>
        </x-ui.card>

        @include('pyramid::partials.feed', ['feed' => $feed, 'labels' => $labels])
    </aside>
</div>
@endsection
