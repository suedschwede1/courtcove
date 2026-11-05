{{--
    The viewer's running challenges. $variant 'card' is the side card (tablet and up),
    'banner' the highlighted card above the list on phones (only when there is something to show).
    Needs: $variant, $viewer, $isLoggedIn, $openChallenges, $ranking, $labels.
--}}
@php
    use App\Modules\Pyramid\Support\PyramidFormat;
    use App\Modules\Pyramid\Support\PyramidLinks;

    $mine = $viewer === null
        ? collect()
        : $openChallenges->filter(
            fn ($challenge) => (int) $challenge->challenger_id === (int) $viewer->id || (int) $challenge->opponent_id === (int) $viewer->id
        );
    $positions = $ranking->pluck('position', 'id');

    $entries = $mine->map(function ($challenge) use ($viewer, $labels, $positions): array {
        $otherId = (int) $challenge->challenger_id === (int) $viewer->id ? (int) $challenge->opponent_id : (int) $challenge->challenger_id;
        $name = $labels[$otherId] ?? __('pyramid::ui.unknown');
        $position = $positions[$otherId] ?? null;
        $when = $challenge->scheduled_at ? PyramidFormat::dateTime($challenge->scheduled_at) : __('pyramid::ui.mine.date_open');
        $deadline = $challenge->deadline ? __('pyramid::ui.mine.deadline', ['date' => PyramidFormat::date($challenge->deadline)]) : null;

        return [
            'href' => PyramidLinks::challenge((int) $challenge->id),
            'title' => $position === null
                ? __('pyramid::ui.mine.against', ['name' => $name])
                : __('pyramid::ui.mine.against_pos', ['name' => $name, 'position' => $position]),
            'scheduled' => (bool) $challenge->scheduled_at,
            'line' => $deadline === null ? $when : $when.' · '.$deadline,
        ];
    });
@endphp
@if ($variant === 'banner')
    @if ($entries->isNotEmpty())
        <x-ui.card tone="highlight" :eyebrow="__('pyramid::ui.mine.eyebrow')" class="md:hidden">
            <div class="flex flex-col gap-3">
                @foreach ($entries as $entry)
                    <a href="{{ $entry['href'] }}" class="flex flex-col gap-1 text-current no-underline">
                        <span class="text-body-l font-bold">{{ $entry['title'] }}</span>
                        <span class="text-chrome-ink">{{ $entry['line'] }}</span>
                    </a>
                @endforeach
            </div>
        </x-ui.card>
    @endif
@else
    <x-ui.card :title="__('pyramid::ui.mine.title')" class="hidden md:block">
        <div class="flex flex-col gap-2.5">
            @if ($viewer === null && ! $isLoggedIn)
                <p class="text-ink-2">{{ __('pyramid::ui.mine.login') }}</p>
            @elseif ($entries->isEmpty())
                <p class="text-ink-2">{{ __('pyramid::ui.mine.none') }}</p>
            @else
                @foreach ($entries as $entry)
                    <a href="{{ $entry['href'] }}" class="flex flex-col gap-1 rounded-lg bg-ground p-3 text-ink no-underline">
                        <span class="flex items-start justify-between gap-2">
                            <strong>{{ $entry['title'] }}</strong>
                            @if ($entry['scheduled'])
                                <x-ui.badge tone="neutral">{{ __('pyramid::ui.mine.state_scheduled') }}</x-ui.badge>
                            @else
                                <x-ui.badge tone="attention">{{ __('pyramid::ui.mine.state_open') }}</x-ui.badge>
                            @endif
                        </span>
                        <span class="text-body-s text-ink-2">{{ $entry['line'] }}</span>
                    </a>
                @endforeach
            @endif
            <a href="{{ PyramidLinks::challenges() }}" class="font-semibold">{{ __('pyramid::ui.mine.all') }}</a>
        </div>
    </x-ui.card>
@endif
