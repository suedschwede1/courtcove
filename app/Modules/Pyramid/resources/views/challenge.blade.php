@extends('pyramid::layout')

@section('title', __('pyramid::ui.challenges.pair', ['challenger' => $challenger->label, 'opponent' => $opponent->label]))

@section('pyramid-content')
@php
    use App\Modules\Pyramid\Support\PyramidLinks;
    use App\Modules\Pyramid\Support\PyramidStatus;

    // Stored dates are plain strings: a broken one renders as a dash instead of failing the page.
    $format = static function (?string $sql, string $pattern): string {
        $time = $sql === null || trim($sql) === '' ? false : strtotime($sql);

        return $time ? date($pattern, $time) : '–';
    };

    $a = $challenge->challenger_pos;
    $b = $challenge->opponent_pos;
    $ranks = match (true) {
        $a === null && $b === null => __('pyramid::ui.challenges.ranks_both_waiting'),
        $a === null => __('pyramid::ui.challenges.ranks_challenger_waiting', ['b' => $b]),
        $b === null => __('pyramid::ui.challenges.ranks_opponent_waiting', ['a' => $a]),
        default => __('pyramid::ui.challenges.ranks', ['a' => $a, 'b' => $b]),
    };
    $ranksLine = $ranks.' · '.__('pyramid::ui.challenges.deadline', ['date' => $format($challenge->deadline, 'd.m.Y')]);
    $running = $challenge->isRunning();
    $declineReasons = ['other_match' => __('pyramid::ui.detail.reason_other_match'), 'injury' => __('pyramid::ui.detail.reason_injury')];
@endphp
@include('pyramid::partials.header', [
    'ladder' => $ladder,
    'ladders' => $ladders,
    'active' => 'ranking',
    'canAdmin' => $canAdmin,
])

<div class="mx-auto flex w-full max-w-2xl flex-col gap-4">
    <x-ui.card :aria-label="__('pyramid::ui.challenges.title')">
        <div class="flex flex-col gap-2.5">
            <x-ui.badge :tone="PyramidStatus::badgeGroup((string) $challenge->status)" class="self-start">{{ PyramidStatus::label((string) $challenge->status) }}</x-ui.badge>
            <h2 class="break-words font-display text-display-l leading-none text-ink">{{ __('pyramid::ui.challenges.pair', ['challenger' => $challenger->label, 'opponent' => $opponent->label]) }}</h2>
            <p class="text-ink-2">{{ $ranksLine }}</p>

            @if ($challenge->scheduled_at)
                <p class="font-semibold">{{ __('pyramid::ui.detail.date_line', ['date' => $format($challenge->scheduled_at, 'd.m.Y H:i')]) }}</p>
            @endif

            @if ($challenge->score)
                <p class="font-semibold">{{ __('pyramid::ui.detail.result_line', ['score' => $challenge->score]) }}</p>
            @endif

            @if ($contact !== null)
                <p class="text-ink-2">{{ __('pyramid::ui.detail.contact_with', ['name' => $contact['name']]) }}: {{ $contact['phone'] }}</p>
                <div class="grid grid-cols-2 gap-2">
                    <x-ui.button variant="secondary" :href="'tel:'.$contact['tel']">{{ __('pyramid::ui.detail.call') }}</x-ui.button>
                    @if ($contact['whatsapp'] !== null)
                        <x-ui.button variant="secondary" :href="$contact['whatsapp']" target="_blank" rel="noopener">{{ __('pyramid::ui.detail.whatsapp') }}</x-ui.button>
                    @endif
                </div>
            @endif

            @if ($adminContacts !== [])
                <div class="flex flex-col gap-1">
                    <p class="font-semibold">{{ __('pyramid::ui.detail.contact_admin') }}</p>
                    @foreach ($adminContacts as $entry)
                        <p class="text-ink-2">{{ $entry['name'] }}: {{ $entry['phone'] ?? __('pyramid::ui.detail.no_phone') }}</p>
                    @endforeach
                </div>
            @endif
        </div>
    </x-ui.card>

    @if ($challenge->status === 'played')
        <x-ui.notice tone="good">{{ __('pyramid::ui.detail.played_note') }}</x-ui.notice>
    @endif

    @if ($isInvolved && $running)
        @if ($challenge->status === 'open')
            <x-ui.card :title="__('pyramid::ui.detail.schedule_title')" level="2">
                <form method="POST" action="{{ route('pyramide.challenge.schedule', ['challengeId' => (int) $challenge->id]) }}" class="flex flex-col gap-4">
                    @csrf
                    <x-ui.field for="pyramid-scheduled-at" :label="__('pyramid::ui.challenge.scheduled_at')" :help="__('pyramid::ui.detail.schedule_help')">
                        <x-ui.input type="datetime-local" name="scheduled_at" id="pyramid-scheduled-at" required />
                    </x-ui.field>
                    <x-ui.button type="submit">{{ __('pyramid::ui.detail.schedule_submit') }}</x-ui.button>
                </form>
            </x-ui.card>
        @else
            <x-ui.card :title="__('pyramid::ui.detail.result_title')" level="2">
                @include('pyramid::partials.score-form', [
                    'challenge' => $challenge,
                    'challenger' => $challenger,
                    'opponent' => $opponent,
                    'scoreHint' => $scoreHint,
                ])
            </x-ui.card>
        @endif

        @if (! $isChallenger)
            <x-ui.card :title="__('pyramid::ui.detail.decline_title')" level="2">
                <form method="POST" action="{{ route('pyramide.challenge.decline', ['challengeId' => (int) $challenge->id]) }}" class="flex flex-col gap-4">
                    @csrf
                    <p class="text-ink-2">{{ __('pyramid::ui.detail.decline_help') }}</p>
                    <x-ui.option-group :legend="__('pyramid::ui.detail.decline_reason')" :columns="2">
                        @foreach ($declineReasons as $value => $label)
                            <x-ui.option-tile name="reason" :value="$value" :label="$label" :checked="$loop->first" />
                        @endforeach
                    </x-ui.option-group>
                    <x-ui.button type="submit" variant="danger">{{ __('pyramid::ui.detail.decline_submit') }}</x-ui.button>
                </form>
            </x-ui.card>
        @else
            <form method="POST" action="{{ route('pyramide.challenge.withdraw', ['challengeId' => (int) $challenge->id]) }}">
                @csrf
                <x-ui.button type="submit" variant="text" class="text-danger">{{ __('pyramid::ui.detail.withdraw') }}</x-ui.button>
            </form>
        @endif
    @endif

    <x-ui.button variant="secondary" :href="PyramidLinks::ladder((int) $ladder->id)">{{ __('pyramid::ui.detail.back') }}</x-ui.button>
</div>
@endsection
