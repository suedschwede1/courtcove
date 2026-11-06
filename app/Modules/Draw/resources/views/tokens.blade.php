{{--
    Token pick (old draw/token/index.phtml, mockup Turnier-Token-Mobil): draw title, the procedure note with
    the record link, the test tournament note, the own token as a highlight card (or why the viewer does not
    pick), then the tokens in position order as a card grid. Blocked tokens (partner rule) are left out
    entirely, without a reason; the header then names what this player can take. Slots show only on claimed
    tokens: on free ones the list would be a menu. The pick is two-step: "wählen" opens the confirm modal.
    Needs: $c (TokenPageContext), $draw, $tokens (rows), $players, $own, $free, $selectable, $hiddenCount
    plus the tournament::layout context.
--}}
@extends('tournament::layout')

@section('tournament-content')
@php
    $open = $draw->status === 'picking';
    $player = $c->player;
    $testModeLocked = $c->access->simulation !== null && ! $c->canPick;
@endphp
<div class="flex flex-col gap-4" x-data="{ pending: null }">
    <h2 class="font-display text-display-m text-ink">{{ $draw->title }}</h2>

    <x-ui.notice tone="neutral">
        <p class="text-ink-2">
            {{ __('draw::tokens.info_lead') }} <strong class="text-ink">{{ __('draw::tokens.info_hidden') }}</strong>{{ __('draw::tokens.info_rest') }}
            {{ __('draw::tokens.info_record') }} <a href="{{ route('draw.record', ['slug' => $draw->slug]) }}" class="font-semibold">{{ __('draw::tokens.record_link') }}</a>.
        </p>
    </x-ui.notice>

    @if ($c->isTest && $c->access->simulation !== null && $c->canPick && $player !== null)
        <x-ui.notice tone="attention" :title="__('draw::tokens.test_title')">
            <p>{{ __('draw::tokens.test_as', ['label' => $player['label']]) }} <span class="text-ink-2">{{ __('draw::tokens.test_note') }}</span></p>
        </x-ui.notice>
    @endif

    @if ($own !== null)
        <x-ui.card as="div" tone="highlight" :eyebrow="__('draw::tokens.own_label')">
            <div class="flex flex-col gap-1.5">
                <p class="font-display text-display-l">{{ $own['token'] }}</p>
                @if ($own['slot'])
                    <p class="text-body font-bold">{{ __('draw::tokens.own_group', ['slot' => $own['slot']]) }}</p>
                @endif
                <p class="text-body-s">{{ __('draw::tokens.own_claimed', ['date' => date('d.m.Y H:i', (int) strtotime((string) $own['claimed']))]) }}</p>
            </div>
        </x-ui.card>
    @elseif ($testModeLocked)
        <x-ui.notice tone="attention">{{ __('draw::tokens.pick.test_mode') }}</x-ui.notice>
    @elseif ($player === null)
        <x-ui.notice tone="attention">{{ __('draw::tokens.not_player') }}</x-ui.notice>
    @elseif (! $c->eligible)
        <x-ui.notice tone="attention">{{ __('draw::tokens.not_eligible') }}</x-ui.notice>
    @elseif ($open)
        <p class="text-body text-ink-2">
            <strong class="text-ink">{{ __('draw::tokens.none_yet') }}</strong>
            @if ($draw->pick_deadline)
                {{ __('draw::tokens.until', ['deadline' => date('d.m.Y H:i', (int) strtotime((string) $draw->pick_deadline))]) }}
            @endif
            {{ __('draw::tokens.fallback_note') }}
        </p>
    @endif

    <div class="flex items-baseline justify-between gap-3">
        <h3 class="font-display text-display-m text-ink">{{ __('draw::tokens.heading') }}</h3>
        <span class="text-body-s text-ink-2">
            @if ($hiddenCount)
                {{ __('draw::tokens.selectable', ['count' => $selectable]) }}
            @else
                {{ __('draw::tokens.free_of', ['free' => $free, 'total' => count($tokens)]) }}
            @endif
        </span>
    </div>

    <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($tokens as $token)
            @continue(isset($c->blockedTokens[(int) $token['dtid']]))
            @php
                $takenBy = $token['claimed_tpid'] ? ($players[(int) $token['claimed_tpid']] ?? null) : null;
                $isOwn = $own !== null && (int) $own['dtid'] === (int) $token['dtid'];
                $pickable = $open && $player !== null && $c->eligible && $own === null && ! $token['claimed_tpid'];
            @endphp
            <li>
                <x-ui.card as="article" padding="md" class="h-full">
                    <div class="flex h-full flex-col gap-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-mono text-body font-bold text-ink">{{ $token['token'] }}</span>
                            @if ($isOwn)
                                <x-ui.badge tone="you">{{ __('draw::tokens.own_label') }}</x-ui.badge>
                            @endif
                        </div>
                        @if ($pickable)
                            <x-ui.button aria-label="{{ __('draw::tokens.confirm_title', ['token' => $token['token']]) }}" x-on:click="pending = {{ \Illuminate\Support\Js::from(['dtid' => (int) $token['dtid'], 'title' => __('draw::tokens.confirm_title', ['token' => $token['token']])]) }}">{{ __('draw::tokens.pick_button') }}</x-ui.button>
                        @elseif ($token['claimed_tpid'])
                            <p class="text-body-s text-ink">
                                {{ $takenBy !== null ? $takenBy['label'] : __('draw::tokens.taken') }}
                                @if ($token['assigned_by'] === 'fallback')
                                    <span class="text-ink-3">· {{ __('draw::tokens.assigned') }}</span>
                                @endif
                            </p>
                            @if ($token['slot'])
                                <p class="text-body-s text-ink-3">{{ __('draw::tokens.group', ['slot' => $token['slot']]) }}</p>
                            @endif
                        @else
                            <p class="text-body-s text-ink-3">{{ __('draw::tokens.free') }}</p>
                        @endif
                    </div>
                </x-ui.card>
            </li>
        @endforeach
    </ul>

    <p class="text-body-s text-ink-3">{{ __('draw::tokens.order_note') }}</p>

    <x-ui.modal id="token-confirm" show="pending !== null" close="pending = null" :label="__('draw::tokens.confirm_submit')">
        <x-slot:title><span x-text="pending ? pending.title : ''"></span></x-slot:title>
        <form method="POST" id="token-confirm-form" action="{{ route('draw.tokens.pick', ['did' => $c->did]) }}" class="flex flex-col gap-4">
            @csrf
            <input type="hidden" name="dtid" x-bind:value="pending ? pending.dtid : ''">
            <p class="text-ink-2">{{ __('draw::tokens.confirm_text') }}</p>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" :block="false" x-on:click="pending = null">{{ __('draw::tokens.cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="token-confirm-form" :block="false" data-modal-initial>{{ __('draw::tokens.confirm_submit') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
@endsection
