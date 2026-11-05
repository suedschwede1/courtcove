{{--
    The event feed. The text is rendered here from the stored payload (not stored itself), so wording
    changes need no migration. Stored German texts of 'admin' events are printed as they are.
    Needs: $feed (PyramidEvent collection), $labels (player id => label).
--}}
@php
    use App\Modules\Pyramid\Support\EventText;
    use App\Modules\Pyramid\Support\PyramidFormat;

    $name = fn ($id) => $labels[(int) $id] ?? __('pyramid::ui.unknown');
    $lines = $feed->map(function ($event) use ($name): array {
        $data = $event->data();

        $text = match ($event->type) {
            'challenge' => __('pyramid::ui.feed.challenge', [
                'challenger' => $name($data['challenger'] ?? 0),
                'opponent' => $name($data['opponent'] ?? 0),
                'position' => (int) ($data['opponent_pos'] ?? 0),
            ]),
            'schedule' => __('pyramid::ui.feed.schedule', [
                'challenger' => $name($data['challenger'] ?? 0),
                'opponent' => $name($data['opponent'] ?? 0),
                // The payload is stored data: a broken value renders as a dash instead of failing the page.
                'day' => PyramidFormat::safeDate(is_scalar($data['scheduled_at'] ?? null) ? (string) $data['scheduled_at'] : null),
                'time' => PyramidFormat::safeTime(is_scalar($data['scheduled_at'] ?? null) ? (string) $data['scheduled_at'] : null),
            ]),
            'result' => __('pyramid::ui.feed.result', [
                'winner' => $name($data['winner'] ?? 0),
                'loser' => $name($data['loser'] ?? 0),
                'score' => ($data['score'] ?? '') === EventText::FORFEIT_SCORE ? __('pyramid::ui.feed.forfeit_score') : (string) ($data['score'] ?? ''),
            ]),
            'join' => isset($data['position'])
                ? __('pyramid::ui.feed.join_position', ['player' => $name($data['player'] ?? 0), 'position' => (int) $data['position']])
                : __('pyramid::ui.feed.join', ['player' => $name($data['player'] ?? 0)]),
            default => $data['message'] ?? __('pyramid::ui.feed.admin'),
        };

        return ['text' => $text, 'when' => __('pyramid::ui.feed.when', ['date' => PyramidFormat::safeDateTime($event->created)])];
    });
@endphp
<x-ui.card :title="__('pyramid::ui.feed.title')">
    @if ($lines->isEmpty())
        <p class="text-ink-2">{{ __('pyramid::ui.feed.none') }}</p>
    @else
        <ul class="flex flex-col gap-2.5">
            @foreach ($lines as $line)
                <li class="flex flex-col gap-0.5 border-t border-line-soft pt-2">
                    <span class="font-semibold">{{ $line['text'] }}</span>
                    <span class="text-body-s text-ink-3">{{ $line['when'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-ui.card>
