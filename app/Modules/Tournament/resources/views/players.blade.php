{{--
    Participants (old index/players.phtml, mockup Turnier-Teilnehmer-Mobil): seed list as cards with an
    expandable detail part (<details>, no script needed), search and group filter in Alpine (replaces the old
    inline script; without JavaScript every player stays visible). Contacts only for participants and
    admins, the ITN of other players only for admins.
--}}
@extends('tournament::layout')

@section('tournament-content')
@php
    use App\Modules\TournamentBase\Support\WhatsApp;

    $count = count($players);
    $isAdmin = $access->isAdmin();
    $itn = fn (array $p): string => number_format((float) $p['itn_rated'], 1, ',', '');
    $competitionOf = function (int $seed) use ($competitions): ?array {
        foreach ($competitions as $comp) {
            if ($seed >= $comp['from'] && $seed <= $comp['to']) {
                return $comp;
            }
        }

        return null;
    };
    $filterData = collect($players)->map(fn (array $p): array => ['name' => mb_strtolower((string) $p['label']), 'group' => $p['grp'] ?: '-'])->values()->all();
@endphp

<div class="flex flex-col gap-3"
     x-data="{ term: '', group: '', players: @js($filterData),
               matches(name, group) { return (this.term.trim() === '' || name.includes(this.term.trim().toLowerCase())) && (this.group === '' || group === this.group); },
               get shown() { return this.players.filter(p => this.matches(p.name, p.group)).length; } }">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="font-display text-display-m text-ink">{{ __('tournament::players.title') }}</h2>
        <p class="text-body-s text-ink-2">{{ __('tournament::players.by_itn') }} · <span x-text="shown">{{ $count }}</span> {{ __('tournament::players.of', ['count' => $count]) }}</p>
    </div>

    <label for="player-search" class="sr-only">{{ __('tournament::players.search_label') }}</label>
    <x-ui.input type="search" name="q" id="player-search" x-model="term" placeholder="{{ __('tournament::players.search') }}" autocomplete="off" />

    @if ($groups)
        <div role="group" aria-label="{{ __('tournament::players.groups_label') }}" class="flex flex-wrap gap-2">
            <x-ui.chip :active="true" x-on:click="group = ''" x-bind:aria-pressed="group === '' ? 'true' : 'false'">{{ __('tournament::players.all') }}</x-ui.chip>
            @foreach ($groups as $g)
                <x-ui.chip x-on:click="group = '{{ $g }}'" x-bind:aria-pressed="group === '{{ $g }}' ? 'true' : 'false'">{{ __('tournament::players.group', ['group' => $g]) }}</x-ui.chip>
            @endforeach
        </div>
    @endif

    <p class="text-body-s text-ink-2" x-cloak x-show="shown === 0">{{ __('tournament::players.no_match') }}</p>

    <ol class="flex flex-col gap-2">
        @foreach ($players as $player)
            @php
                $tpid = (int) $player['tpid'];
                $isMe = $access->tpid !== null && $tpid === $access->tpid;
                $bal = $balance[$tpid] ?? null;
                $comp = $competitionOf((int) $player['seed']);
                $email = $showContacts && $player['uid'] ? ($emails[(int) $player['uid']] ?? null) : null;
                $phone = $showContacts ? trim((string) $player['phone']) : '';
                $wa = $phone !== '' ? WhatsApp::url($phone) : '';
                $line = array_filter([
                    $isAdmin ? __('tournament::players.itn', ['itn' => $itn($player)]) : null,
                    $player['grp'] ? __('tournament::players.group', ['group' => $player['grp']]) : __('tournament::players.not_drawn'),
                ]);
            @endphp
            <li data-name="{{ mb_strtolower((string) $player['label']) }}" data-group="{{ $player['grp'] ?: '-' }}"
                x-show="matches($el.dataset.name, $el.dataset.group)">
                <x-ui.card as="article" padding="none">
                    <details class="group">
                        <summary class="flex min-h-12 cursor-pointer list-none items-center gap-3 px-4 py-3">
                            <span class="w-7 shrink-0 text-right font-display text-display-s tabular-nums text-ink-2">{{ (int) $player['seed'] }}</span>
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="truncate font-bold text-ink">{{ $player['label'] }}</span>
                                <span class="text-body-s text-ink-2">{{ implode(' · ', $line) }}</span>
                                @if ($player['note'])
                                    <span class="truncate text-body-s text-warn-ink">{{ $player['note'] }}</span>
                                @endif
                            </span>
                            @if ($isMe)
                                <x-ui.badge tone="you">{{ __('tournament::players.you') }}</x-ui.badge>
                            @endif
                            @if ($player['grp_pos'] !== null && (int) $player['grp_pos'] === 1)
                                <x-ui.badge tone="good">{{ __('tournament::players.seeded') }}</x-ui.badge>
                            @elseif ($comp !== null && $comp['range'] !== '')
                                <x-ui.badge tone="neutral">{{ $comp['range'] }}</x-ui.badge>
                            @endif
                            <x-ui.icon name="chevron-down" class="shrink-0 text-ink-3 transition-transform group-open:rotate-180" />
                        </summary>

                        <div class="flex flex-col gap-3 border-t border-line px-4 py-3">
                            <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-body-s sm:grid-cols-4">
                                @if ($isAdmin)
                                    <dt class="text-ink-2">{{ __('tournament::players.itn_label') }}</dt>
                                    <dd>{{ $itn($player) }}@if ($player['itn'] === null) <span class="text-ink-2">· {{ __('tournament::players.itn_default', ['itn' => number_format((float) $tournament->itn_default, 1, ',', '')]) }}</span>@endif</dd>
                                @endif
                                <dt class="text-ink-2">{{ __('tournament::players.gender') }}</dt>
                                <dd>{{ in_array($player['gender'], ['m', 'w'], true) ? __('tournament::players.genders.'.$player['gender']) : '–' }}</dd>
                                <dt class="text-ink-2">{{ __('tournament::players.seed') }}</dt>
                                <dd>{{ __('tournament::players.seed_of', ['seed' => (int) $player['seed'], 'count' => $count]) }}</dd>
                                <dt class="text-ink-2">{{ __('tournament::players.competition') }}</dt>
                                <dd>{{ $comp !== null ? $comp['label'].($comp['range'] !== '' ? ' ('.$comp['range'].')' : '') : '–' }}</dd>
                                <dt class="text-ink-2">{{ __('tournament::players.group_label') }}</dt>
                                <dd>
                                    @if (! $player['grp'])
                                        {{ __('tournament::players.from_pot_open') }}
                                    @elseif ((int) $player['grp_pos'] === 1)
                                        {{ __('tournament::players.head', ['group' => $player['grp']]) }}
                                    @else
                                        {{ __('tournament::players.from_pot', ['group' => $player['grp']]) }}
                                    @endif
                                </dd>
                                @if ($bal)
                                    <dt class="text-ink-2">{{ __('tournament::players.balance') }}</dt>
                                    <dd>{{ __('tournament::players.wins', ['wins' => $bal['wins'], 'losses' => $bal['losses']]) }}@if ($bal['open']) · {{ __('tournament::players.open', ['count' => $bal['open']]) }}@endif</dd>
                                @endif
                            </dl>

                            @if ($showContacts)
                                <dl class="grid grid-cols-2 gap-x-3 gap-y-1 border-t border-line pt-3 text-body-s sm:grid-cols-4">
                                    <dt class="text-ink-2">{{ __('tournament::players.phone') }}</dt>
                                    <dd>
                                        @if ($phone !== '')
                                            <a class="text-clay underline hover:text-clay-hover" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a>
                                        @else
                                            <span class="text-ink-2">{{ __('tournament::players.missing') }}</span>
                                        @endif
                                    </dd>
                                    <dt class="text-ink-2">{{ __('tournament::players.email') }}</dt>
                                    <dd class="break-all">
                                        @if ($email)
                                            <a class="text-clay underline hover:text-clay-hover" href="mailto:{{ $email }}">{{ $email }}</a>
                                        @else
                                            <span class="text-ink-2">{{ __('tournament::players.missing') }}</span>
                                        @endif
                                    </dd>
                                </dl>
                                @if ($wa !== '' || $email)
                                    <div class="flex flex-wrap gap-2">
                                        @if ($wa !== '')
                                            <x-ui.button variant="secondary" :block="false" :href="$wa" target="_blank" rel="noopener">{{ __('tournament::players.whatsapp') }}</x-ui.button>
                                        @endif
                                        @if ($email)
                                            <x-ui.button variant="secondary" :block="false" :href="'mailto:'.$email">{{ __('tournament::players.email') }}</x-ui.button>
                                        @endif
                                    </div>
                                @endif
                            @endif
                        </div>
                    </details>
                </x-ui.card>
            </li>
        @endforeach
    </ol>

    <p class="text-body-s text-ink-2">
        @foreach ($competitions as $comp)
            {{ __('tournament::players.competition_range', ['from' => $comp['from'], 'to' => $comp['to'], 'label' => $comp['label']]) }}
        @endforeach
        {{ __('tournament::players.itn_note', ['itn' => number_format((float) $tournament->itn_default, 1, ',', '')]) }}
        @unless ($showContacts)
            <br>{{ __('tournament::players.contacts_hidden') }}
        @endunless
    </p>
</div>
@endsection
