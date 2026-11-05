{{--
    Group cards of a proposed Americano round with the swap picker (old round.phtml "Spieler tauschen"):
    tap two players of DIFFERENT groups, a confirm bar ":a und :b tauschen?" with Tauschen / Abbrechen
    appears and posts participant_a / participant_b to mixed.admin.swap. A second tap in the same group moves
    the selection there. Extra-group fillers (2×) are disabled: their two rows make a swap ambiguous and the
    service refuses it. Never used for Mexicano (only the sitter swap applies there).
    Without $canSwap (scorers) the chips are plain badges.
    Needs: $round, $byGroup, $groupPlayers, $labels, $genders, $playsTwice, $waves, $waveCount, $canSwap.
--}}
@php
    use App\Modules\MixedTournament\Support\MixedFormat;

    $unknown = __('mixed::common.unknown_player');
    $question = __('mixed::admin.round.swap_question', ['a' => '__A__', 'b' => '__B__']);
    $title = static function (int $groupNo) use ($byGroup, $waves, $waveCount): string {
        $parts = [__('mixed::common.group', ['number' => $groupNo + 1])];
        if ((bool) $byGroup[$groupNo][0]->is_extra) {
            $parts[] = __('mixed::common.extra_group');
        }
        if ($waveCount > 1 && isset($waves[$groupNo])) {
            $parts[] = __('mixed::common.wave', ['number' => $waves[$groupNo]]);
        }

        return implode(' · ', $parts);
    };
@endphp
<section aria-label="{{ __('mixed::admin.round.swap_title') }}" class="flex flex-col gap-3.5"
    @if ($canSwap)
        x-data="{
            a: null,
            b: null,
            question: @js($question),
            pick(d) {
                const p = { id: d.id, name: d.name, group: d.group };
                if (this.a && this.a.id === p.id) { this.a = this.b; this.b = null; return; }
                if (this.b && this.b.id === p.id) { this.b = null; return; }
                if (this.a === null || this.b !== null || this.a.group === p.group) { this.a = p; this.b = null; return; }
                this.b = p;
            },
            selected(id) { return (this.a !== null && this.a.id === String(id)) || (this.b !== null && this.b.id === String(id)); },
            reset() { this.a = null; this.b = null; },
        }"
    @endif
>
    @if ($canSwap)
        <x-ui.notice tone="attention">
            <p>{{ __('mixed::admin.round.swap_hint') }}</p>
            @if ($playsTwice !== [])
                <p class="text-body-s">{{ __('mixed::admin.round.swap_filler_hint') }}</p>
            @endif
        </x-ui.notice>
    @endif

    <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,16rem),1fr))] gap-3">
        @foreach ($groupPlayers as $groupNo => $players)
            <x-ui.card as="article">
                <div class="flex flex-col gap-2.5">
                    <h3 class="font-display text-display-s text-ink">{{ $title($groupNo) }}</h3>
                    <ul class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        @foreach ($players as $pid)
                            @php
                                $filler = in_array($pid, $playsTwice, true);
                                $label = $labels[$pid] ?? $unknown;
                                $chip = __('mixed::admin.round.player_chip', ['name' => $label, 'gender' => MixedFormat::gender($genders[$pid] ?? null)]).($filler ? ' · '.__('mixed::common.twice') : '');
                            @endphp
                            <li class="flex min-w-0">
                                @if (! $canSwap)
                                    <x-ui.badge :tone="$filler ? 'attention' : 'neutral'">{{ $chip }}</x-ui.badge>
                                @elseif ($filler)
                                    <x-ui.chip disabled class="w-full min-w-0 cursor-not-allowed justify-center opacity-60 sm:w-auto"><span class="min-w-0 truncate">{{ $chip }}</span></x-ui.chip>
                                @else
                                    <x-ui.chip class="w-full min-w-0 justify-center sm:w-auto" data-id="{{ $pid }}" data-name="{{ $label }}" data-group="{{ $groupNo }}" x-on:click="pick($el.dataset)" x-bind:aria-pressed="selected({{ $pid }}) ? 'true' : 'false'"><span class="min-w-0 truncate">{{ $chip }}</span></x-ui.chip>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </x-ui.card>
        @endforeach
    </div>

    @if ($canSwap)
        <form method="POST" action="{{ route('mixed.admin.swap', ['round' => (int) $round->id]) }}" x-show="b !== null" x-cloak
              class="sticky bottom-3 z-10 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line-strong bg-surface p-3">
            @csrf
            <input type="hidden" name="participant_a" x-bind:value="a ? a.id : ''">
            <input type="hidden" name="participant_b" x-bind:value="b ? b.id : ''">
            <p class="min-w-0 font-semibold text-ink" aria-live="polite" x-text="b ? question.replace('__A__', a.name).replace('__B__', b.name) : ''"></p>
            <div class="flex gap-2.5">
                <x-ui.button type="submit" :block="false">{{ __('mixed::admin.round.swap_submit') }}</x-ui.button>
                <x-ui.button variant="secondary" :block="false" x-on:click="reset()">{{ __('mixed::admin.round.swap_cancel') }}</x-ui.button>
            </div>
        </form>
    @endif
</section>
