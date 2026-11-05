{{--
    The ranking: a centred pyramid from tablet width up, a list grouped by level on phones.
    Needs: $rows (level => players), $blocked (player id => Verdict|null), $viewer (PyramidPlayer|null).
    Every player links to the profile, never straight into a challenge; the verdict message is
    always in the text (title and screen-reader text) so the colour is never the only signal.
--}}
@php
    use App\Modules\Pyramid\Support\PyramidFormat;
    use App\Modules\Pyramid\Support\PyramidLinks;

    // Reasons with a short phone-row hint of their own; every other reason stays in the title and screen-reader text.
    $inlineHints = [
        'pyramid::check.opponent_open' => 'pyramid::ui.inline.opponent_open',
        'pyramid::check.protected' => 'pyramid::ui.inline.protected',
    ];

    $cellsByRow = [];
    foreach ($rows as $level => $players) {
        foreach ($players as $player) {
            $id = (int) $player->id;
            $verdict = $blocked[$id] ?? null;
            $isSelf = $viewer !== null && (int) $viewer->id === $id;
            $isPaused = $player->status === 'paused';
            $state = match (true) {
                $isPaused => 'blocked',
                $isSelf => 'self',
                $verdict === null => 'open',
                default => 'blocked',
            };
            $cellsByRow[$level][] = [
                'id' => $id,
                'position' => (int) $player->position,
                'label' => $player->label,
                // Abbreviated like the mockup ("K. Huber"); the viewer's own cell keeps the full name.
                'name' => $isSelf ? (string) $player->label : PyramidFormat::shortName($player->label),
                'itn' => PyramidFormat::itn($player->itn),
                'state' => $state,
                'paused' => $isPaused,
                'canChallenge' => ! $isPaused && ! $isSelf && $verdict === null,
                'message' => $isSelf ? null : ($verdict === null ? __('pyramid::ui.challengeable') : $verdict->message()),
                'hint' => ! $isSelf && ! $isPaused && $verdict !== null && isset($inlineHints[$verdict->key]) ? __($inlineHints[$verdict->key]) : null,
            ];
        }
    }

    $cellBase = 'flex h-pyramid-cell-h w-pyramid-cell-w flex-col items-center justify-center rounded-lg px-2.5 py-1.5 text-center leading-tight no-underline';
    $cellTone = [
        'open' => 'bg-court-soft text-court-ink ring-1 ring-inset ring-court',
        'self' => 'bg-clay text-on-clay',
        'blocked' => 'bg-sand text-ink',
    ];
@endphp
<div class="flex flex-col gap-4">
    <ul class="hidden flex-wrap gap-x-5 gap-y-2 text-body-s text-ink-2 md:flex" aria-label="{{ __('pyramid::ui.legend.label') }}">
        <li class="flex items-center gap-2"><span class="h-3 w-4 rounded-sm bg-court-soft ring-1 ring-inset ring-court" aria-hidden="true"></span>{{ __('pyramid::ui.legend.challengeable') }}</li>
        <li class="flex items-center gap-2"><span class="h-3 w-4 rounded-sm bg-clay" aria-hidden="true"></span>{{ __('pyramid::ui.legend.you') }}</li>
        <li class="flex items-center gap-2"><span class="h-3 w-4 rounded-sm bg-sand" aria-hidden="true"></span>{{ __('pyramid::ui.legend.blocked') }}</li>
    </ul>

    <div class="hidden overflow-x-auto md:block">
        <div class="mx-auto flex w-max min-w-full flex-col items-center gap-2">
            @foreach ($cellsByRow as $cells)
                <div class="flex justify-center gap-2">
                    @foreach ($cells as $cell)
                        <a href="{{ PyramidLinks::player($cell['id']) }}" @if ($cell['message'] !== null) title="{{ $cell['message'] }}" @endif class="{{ $cellBase }} {{ $cellTone[$cell['state']] }}">
                            <span class="text-xs font-bold">#{{ $cell['position'] }}@if ($cell['state'] === 'self') · {{ __('pyramid::ui.you') }}@endif</span>
                            <span class="max-w-full break-words text-body font-bold">{{ $cell['name'] }}</span>
                            <span class="text-xs tabular-nums">ITN {{ $cell['itn'] }}</span>
                            @if ($cell['paused'])
                                <span class="text-xs">{{ __('pyramid::ui.paused') }}</span>
                            @endif
                            @if ($cell['message'] !== null)
                                <span class="sr-only">{{ $cell['message'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex flex-col gap-3 md:hidden">
        @foreach ($cellsByRow as $level => $cells)
            <section class="flex flex-col gap-1.5">
                <h3 class="text-label uppercase text-ink-3">{{ __('pyramid::ui.step', ['row' => $level]) }}</h3>
                @foreach ($cells as $cell)
                    <div class="flex min-h-14 items-center gap-3 rounded-lg border py-1.5 pl-3 pr-2 {{ $cell['state'] === 'self' ? 'border-clay bg-clay-soft' : 'border-line bg-surface' }}">
                        <span class="w-7 font-display text-display-m font-bold tabular-nums text-ink-3">{{ $cell['position'] }}</span>
                        <a href="{{ PyramidLinks::player($cell['id']) }}" class="flex min-w-0 flex-1 flex-col text-ink no-underline">
                            <span class="break-words font-bold">{{ $cell['name'] }}</span>
                            <span class="text-body-s text-ink-2">
                                ITN {{ $cell['itn'] }}@if ($cell['paused']) · {{ __('pyramid::ui.paused') }}@endif @if ($cell['hint'] !== null) · {{ $cell['hint'] }}@endif
                                @if ($cell['message'] !== null)<span class="sr-only">{{ $cell['message'] }}</span>@endif
                            </span>
                        </a>
                        @if ($cell['state'] === 'self')
                            <x-ui.badge tone="you">{{ __('pyramid::ui.you') }}</x-ui.badge>
                        @elseif ($cell['canChallenge'])
                            <x-ui.button :href="PyramidLinks::challengeConfirm($cell['id'])" :block="false" class="shrink-0 bg-court text-on-court">{{ __('pyramid::ui.challenge_button') }}</x-ui.button>
                        @endif
                    </div>
                @endforeach
            </section>
        @endforeach
    </div>
</div>
