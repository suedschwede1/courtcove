{{--
    Championship admin "Turnierleitung" (old view/tournament/admin/index.phtml), layout from the
    Admin-Turnierleitung mockup: page head with the counts and the links to the tournament and the draws, section
    chips, then the cards Turnier, Auslosung, Gruppen & KO, Ehepaare & Losentscheid, Testmodus & Zurücksetzen.
    On phones the page opens with "Zu erledigen" and "Bewerbe" (Admin-Turniere-Mobil). Forms whose route
    arrives in a later task are guarded with Route::has. Irreversible actions ask through one shared modal
    instead of the old inline confirm() dialogs. Needs AdminDashboard::build() plus $defaultDeadline,
    $defaultSeason.
--}}
@extends('layouts.admin')

@section('admin-title', __('tournament::admin.index.title'))

@section('admin-content')
@php
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Js;

    $url = static fn (string $name, array $params = []): ?string => Route::has($name) ? route($name, $params) : null;
    $ask = static fn (string $text, string $submit): string => (string) Js::from(['text' => $text, 'submit' => $submit]);
    $statusLabel = match ((string) $tournament->status) {
        'setup' => __('tournament::admin.index.status_setup'),
        'drawn' => __('tournament::admin.index.status_drawn'),
        'running' => __('tournament::admin.index.status_running'),
        'finished' => __('tournament::admin.index.status_finished'),
        default => (string) $tournament->status,
    };
    $drawStatus = static fn (string $status): string => match ($status) {
        'committed' => __('tournament::admin.draws.phase_committed'),
        'executed' => __('tournament::admin.draws.phase_executed'),
        'picking' => __('tournament::admin.draws.phase_picking'),
        'closed' => __('tournament::admin.draws.phase_closed'),
        default => $status,
    };
    $drawBadge = static fn (string $status): string => match ($status) {
        'committed' => __('draw::admin.index.status_committed'),
        'executed' => __('draw::admin.index.status_executed'),
        'picking' => __('draw::admin.index.status_picking'),
        'closed' => __('draw::admin.index.status_closed'),
        default => $status,
    };
    $drawTone = static fn (string $status): string => match ($status) {
        'picking' => 'attention',
        'closed' => 'good',
        default => 'neutral',
    };
    $leadOptions = [
        '2' => __('tournament::admin.draws.lead_2'),
        '15' => __('tournament::admin.draws.lead_15'),
        '30' => __('tournament::admin.draws.lead_30'),
        '120' => __('tournament::admin.draws.lead_120'),
        '1440' => __('tournament::admin.draws.lead_1440'),
    ];
    $bracketLeadOptions = array_diff_key($leadOptions, ['120' => true]);
    $quick = $isTest
        ? [10 => __('tournament::admin.draws.quick_10'), 30 => __('tournament::admin.draws.quick_30'), 1440 => __('tournament::admin.draws.quick_tomorrow')]
        : [120 => __('tournament::admin.draws.quick_2h'), 1470 => __('tournament::admin.draws.quick_tomorrow'), 10110 => __('tournament::admin.draws.quick_week')];
    $canDraw = auth()->user()?->can('admin.draw') ?? false;
    $selectOptions = [];
    foreach ($tournaments as $t) {
        $selectOptions[(string) $t->tid] = $t->title.' ('.trim(implode(', ', array_filter([
            __('tournament::admin.index.option_tid', ['tid' => (int) $t->tid]),
            (string) (int) $t->season,
            $t->is_test ? __('tournament::admin.index.option_test') : null,
            $t->cloned_from ? __('tournament::admin.index.option_copy', ['tid' => (int) $t->cloned_from]) : null,
        ]))).')';
    }
    $templateOptions = [];
    foreach ($tournaments as $t) {
        if (empty($t->is_test)) {
            $templateOptions[(string) $t->tid] = $t->title.' ('.(int) $t->season.')';
        }
    }
    $playerOptions = [];
    foreach ($players as $p) {
        $playerOptions[(string) (int) $p['tpid']] = (string) $p['label'];
    }
    $simulateOptions = [];
    foreach ($players as $p) {
        $simulateOptions[(string) (int) $p['tpid']] = $p['label'].' – '.($p['grp'] ? __('tournament::admin.test.in_group', ['group' => $p['grp']]) : __('tournament::admin.test.not_drawn'));
    }
@endphp
<div class="flex flex-col gap-5" x-data="{ pending: null, ask(form, o) { this.pending = Object.assign({ form: form }, o) }, confirmPending() { const f = this.pending.form; this.pending = null; HTMLFormElement.prototype.submit.call(f) } }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex min-w-0 flex-col gap-1.5">
            <p class="text-label uppercase text-ink-3">{{ __('tournament::admin.index.eyebrow') }}</p>
            <h1 class="font-display text-display-l text-ink md:text-display-xl">{{ $tournament->title }}</h1>
            <p class="text-ink-2">{{ __('tournament::admin.index.facts', ['players' => $playerCount, 'matches' => $matchCount, 'status' => $statusLabel]) }}</p>
        </div>
        <div class="flex flex-wrap gap-2.5">
            <x-ui.button variant="secondary" :block="false" :href="route('tournament.index')">{{ __('tournament::admin.index.to_tournament') }}</x-ui.button>
            @if ($canDraw)
                <x-ui.button variant="secondary" :block="false" :href="route('draw.index')">{{ __('tournament::admin.index.to_draws') }}</x-ui.button>
            @endif
        </div>
    </div>

    @include('tournament-base::partials.flash')

    @if ($isTest)
        <x-ui.notice tone="attention" :title="__('tournament::admin.index.test_title')">{{ __('tournament::admin.index.test_text') }}</x-ui.notice>
    @endif

    {{-- Phones: what needs doing, then the competitions. --}}
    <div class="flex flex-col gap-5 md:hidden">
        @if ($todo !== [])
            <section class="flex flex-col gap-2.5" aria-labelledby="turnierleitung-todo">
                <h2 id="turnierleitung-todo" class="text-label uppercase text-ink-3">{{ __('tournament::admin.todo.title') }}</h2>
                @foreach ($todo as $item)
                    <x-ui.card as="div" :class="'border-l-4 '.($item['tone'] === 'problem' ? 'border-l-danger' : 'border-l-clay')">
                        <a href="#{{ $item['anchor'] }}" class="flex items-center justify-between gap-3 text-ink no-underline">
                            <span class="flex min-w-0 flex-col gap-0.5">
                                <span class="font-semibold">{{ $item['title'] }}</span>
                                <span class="text-body-s text-ink-2">{{ $item['sub'] }}</span>
                            </span>
                            <x-ui.icon name="chevron-right" class="shrink-0 text-ink-3" />
                        </a>
                    </x-ui.card>
                @endforeach
            </section>
        @endif

        @if ($state !== [] || $brackets !== [])
            <section class="flex flex-col gap-2.5" aria-labelledby="turnierleitung-bewerbe">
                <h2 id="turnierleitung-bewerbe" class="text-label uppercase text-ink-3">{{ __('tournament::admin.index.competitions') }}</h2>
                @foreach ($state as $entry)
                    <x-ui.card as="div">
                        <a href="{{ route('tournament.groups') }}" class="flex items-center justify-between gap-3 text-ink no-underline">
                            <span class="flex min-w-0 flex-col gap-0.5">
                                <span class="font-semibold">{{ $entry['phase']['label'] }}</span>
                                <span class="text-body-s text-ink-2">{{ __('tournament::admin.index.matches_played', ['played' => $entry['played'], 'total' => $entry['total']]) }}</span>
                            </span>
                            <x-ui.icon name="chevron-right" class="shrink-0 text-ink-3" />
                        </a>
                    </x-ui.card>
                @endforeach
                @foreach ($brackets as $bracket)
                    <x-ui.card as="div">
                        <a href="{{ route('tournament.bracket', ['phase' => $bracket['phase']['key']]) }}" class="flex items-center justify-between gap-3 text-ink no-underline">
                            <span class="flex min-w-0 flex-col gap-0.5">
                                <span class="font-semibold">{{ $bracket['phase']['label'] }}</span>
                                <span class="text-body-s text-ink-2">{{ __('tournament::admin.index.matches_played', ['played' => $bracket['played'], 'total' => $bracket['matches']]) }}</span>
                            </span>
                            <x-ui.icon name="chevron-right" class="shrink-0 text-ink-3" />
                        </a>
                    </x-ui.card>
                @endforeach
            </section>
        @endif
    </div>

    <nav class="flex gap-2 overflow-x-auto" aria-label="{{ __('tournament::admin.index.sections') }}">
        <x-ui.chip href="#turnierleitung-turnier" class="shrink-0">{{ __('tournament::admin.index.chip_tournament') }}</x-ui.chip>
        <x-ui.chip href="#turnierleitung-auslosung" class="shrink-0">{{ __('tournament::admin.index.chip_draw') }}</x-ui.chip>
        <x-ui.chip href="#turnierleitung-gruppen" class="shrink-0">{{ __('tournament::admin.index.chip_groups') }}</x-ui.chip>
        <x-ui.chip href="#turnierleitung-paare" class="shrink-0">{{ __('tournament::admin.index.chip_couples') }}</x-ui.chip>
        <x-ui.chip href="#turnierleitung-test" class="shrink-0">{{ __('tournament::admin.index.chip_test') }}</x-ui.chip>
    </nav>

    <div class="grid items-start gap-4 lg:grid-cols-3">
        {{-- Turnier: selection, activation, format, generate, test copy, new tournament --}}
        <x-ui.card id="turnierleitung-turnier" :title="__('tournament::admin.index.card_tournament')">
            <div class="flex flex-col gap-4">
                <p class="text-ink">
                    @if ($activeTournament)
                        <strong>{{ __('tournament::admin.index.active_label') }}</strong> {{ $activeTournament->title }} ({{ (int) $activeTournament->season }})
                    @else
                        <strong>{{ __('tournament::admin.index.none_active') }}</strong> {{ __('tournament::admin.index.none_active_text') }}
                    @endif
                </p>

                @if ($selectUrl = $url('tournament.admin.select'))
                    <form method="POST" action="{{ $selectUrl }}" class="flex flex-col gap-2.5">
                        @csrf
                        <x-ui.field for="tl-select" :label="__('tournament::admin.index.select_label')" :help="__('tournament::admin.index.select_help')">
                            <x-ui.select name="tid" id="tl-select" :options="$selectOptions" :selected="(string) $tid" :placeholder="__('tournament::admin.index.select_current')" />
                        </x-ui.field>
                        <div><x-ui.button type="submit" variant="secondary" :block="false">{{ __('tournament::admin.index.select_submit') }}</x-ui.button></div>
                    </form>
                @endif

                @if (! $isTest && empty($tournament->active) && ($activateUrl = $url('tournament.admin.activate')))
                    <form method="POST" action="{{ $activateUrl }}" class="flex flex-col gap-2.5" x-on:submit.prevent="ask($el, {{ $ask(__('tournament::admin.index.activate_confirm'), __('tournament::admin.index.activate')) }})">
                        @csrf
                        <input type="hidden" name="tid" value="{{ $tid }}">
                        <p class="text-body-s text-ink-2">{{ __('tournament::admin.index.not_active') }}</p>
                        <div><x-ui.button type="submit" variant="dark" :block="false">{{ __('tournament::admin.index.activate') }}</x-ui.button></div>
                    </form>
                @endif

                @if ($formatError !== null)
                    <x-ui.notice tone="problem" :title="__('tournament::admin.index.format_broken')">{{ $formatError }} {{ __('tournament::admin.index.format_broken_hint') }}</x-ui.notice>
                @else
                    <x-ui.notice tone="good">{{ __('tournament::admin.index.format_ok') }}</x-ui.notice>
                @endif
                @if ($formatNotes !== [])
                    <ul class="flex flex-col gap-1 text-body-s text-warn-ink">
                        @foreach ($formatNotes as $note)
                            <li>{{ $note }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($generateUrl = $url('tournament.admin.generate'))
                    <form method="POST" action="{{ $generateUrl }}" class="flex flex-col gap-2">
                        @csrf
                        <div><x-ui.button type="submit" :block="false" :disabled="$formatError !== null">{{ __('tournament::admin.index.generate') }}</x-ui.button></div>
                        <p class="text-body-s text-ink-2">{{ __('tournament::admin.index.generate_help') }}</p>
                    </form>
                @endif

                @if (! $isTest && ($cloneUrl = $url('tournament.admin.clone-test')))
                    <form method="POST" action="{{ $cloneUrl }}" class="flex flex-col gap-2">
                        @csrf
                        <div><x-ui.button type="submit" variant="secondary" :block="false">{{ __('tournament::admin.index.clone') }}</x-ui.button></div>
                        <p class="text-body-s text-ink-2">{{ __('tournament::admin.index.clone_help') }}</p>
                    </form>
                @endif

                @if ($createUrl = $url('tournament.admin.create'))
                    <details class="group border-t border-line-soft pt-3">
                        <summary class="flex min-h-12 cursor-pointer list-none items-center gap-2 font-semibold text-ink">
                            <x-ui.icon name="chevron-down" class="shrink-0 transition-transform group-open:rotate-180" />{{ __('tournament::admin.create.summary') }}
                        </summary>
                        <form method="POST" action="{{ $createUrl }}" class="mt-3 flex flex-col gap-3">
                            @csrf
                            <p class="text-body-s text-ink-2">{{ __('tournament::admin.create.intro') }}</p>
                            <p class="text-body-s text-ink-2">{{ __('tournament::admin.create.not_active') }}</p>
                            <x-ui.field for="tl-create-title" :label="__('tournament::admin.create.title')">
                                <x-ui.input name="title" id="tl-create-title" required maxlength="160" :value="__('tournament::admin.create.default_title', ['season' => $defaultSeason])" />
                            </x-ui.field>
                            <x-ui.field for="tl-create-season" :label="__('tournament::admin.create.season')">
                                <x-ui.input type="number" name="season" id="tl-create-season" required min="2000" max="2100" :value="(string) $defaultSeason" />
                            </x-ui.field>
                            <x-ui.field for="tl-create-source" :label="__('tournament::admin.create.source')">
                                <x-ui.select name="source_tid" id="tl-create-source" required :options="$templateOptions" :selected="(string) $tid" />
                            </x-ui.field>
                            <label class="flex items-center gap-2 text-ink">
                                <input type="checkbox" name="with_players" value="1" class="size-5 accent-clay">
                                {{ __('tournament::admin.create.with_players') }}
                            </label>
                            <div><x-ui.button type="submit" :block="false">{{ __('tournament::admin.create.submit') }}</x-ui.button></div>
                        </form>
                    </details>
                @endif
            </div>
        </x-ui.card>

        {{-- Auslosung: token draws per group phase, running draws, unapplied executed draws --}}
        <x-ui.card id="turnierleitung-auslosung" :title="__('tournament::admin.draws.card')">
            <div class="flex flex-col gap-4">
                <p class="text-body-s text-ink-2">{{ __('tournament::admin.draws.flow') }}</p>

                @foreach ($state as $entry)
                    @php $phaseKey = (string) $entry['phase']['key']; @endphp
                    <div class="flex flex-col gap-3">
                        <p class="font-semibold text-ink">{{ __('tournament::admin.draws.pool', ['phase' => $entry['phase']['label'], 'count' => $entry['pool']]) }}</p>

                        @if ($entry['pool'] < 1)
                            <p class="text-body-s text-ink-2">{{ __('tournament::admin.draws.pool_empty') }}</p>
                        @elseif ($prepareUrl = $url('tournament.admin.prepare-draw'))
                            <form method="POST" action="{{ $prepareUrl }}" class="flex flex-col gap-3">
                                @csrf
                                <input type="hidden" name="phase" value="{{ $phaseKey }}">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-ui.field for="tl-deadline-{{ $loop->index }}" :label="__('tournament::admin.draws.deadline')">
                                        <x-ui.input type="datetime-local" name="deadline" id="tl-deadline-{{ $loop->index }}" required step="300" :value="$defaultDeadline" />
                                    </x-ui.field>
                                    @unless ($isTest)
                                        <x-ui.field for="tl-lead-{{ $loop->index }}" :label="__('tournament::admin.draws.lead')">
                                            <x-ui.select name="lead_minutes" id="tl-lead-{{ $loop->index }}" :options="$leadOptions" selected="2" />
                                        </x-ui.field>
                                    @endunless
                                </div>
                                <x-ui.button type="submit">{{ __('tournament::admin.draws.create_tokens') }}</x-ui.button>
                                <p class="text-body-s text-ink-2">{{ $isTest ? __('tournament::admin.draws.test_hint') : __('tournament::admin.draws.lead_hint') }}</p>
                            </form>

                            <div class="flex flex-col gap-1.5">
                                <p class="text-body-s text-ink-2">{{ __('tournament::admin.draws.quick') }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($quick as $minutes => $label)
                                        <form method="POST" action="{{ $prepareUrl }}">
                                            @csrf
                                            <input type="hidden" name="phase" value="{{ $phaseKey }}">
                                            <input type="hidden" name="in_minutes" value="{{ $minutes }}">
                                            <button type="submit" class="ds-chip" aria-label="{{ __('tournament::admin.draws.quick_label', ['when' => $label]) }}">{{ $label }}</button>
                                        </form>
                                    @endforeach
                                </div>
                                <p class="text-body-s text-ink-2">{{ $isTest ? __('tournament::admin.draws.quick_test') : __('tournament::admin.draws.quick_real') }}</p>
                            </div>

                            <details class="group">
                                <summary class="flex min-h-12 cursor-pointer list-none items-center gap-2 text-body-s font-semibold text-ink">
                                    <x-ui.icon name="chevron-down" class="shrink-0 transition-transform group-open:rotate-180" />{{ __('tournament::admin.draws.own_round') }}
                                </summary>
                                <form method="POST" action="{{ $prepareUrl }}" class="mt-2 flex flex-col gap-3">
                                    @csrf
                                    <input type="hidden" name="phase" value="{{ $phaseKey }}">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <x-ui.field for="tl-round-{{ $loop->index }}" :label="__('tournament::admin.draws.round')">
                                            <x-ui.input type="number" name="round" id="tl-round-{{ $loop->index }}" min="1" />
                                        </x-ui.field>
                                        <x-ui.field for="tl-round-deadline-{{ $loop->index }}" :label="__('tournament::admin.draws.deadline')">
                                            <x-ui.input type="datetime-local" name="deadline" id="tl-round-deadline-{{ $loop->index }}" />
                                        </x-ui.field>
                                    </div>
                                    <div><x-ui.button type="submit" variant="secondary" :block="false">{{ __('tournament::admin.draws.create_tokens') }}</x-ui.button></div>
                                </form>
                            </details>
                        @endif
                    </div>
                @endforeach

                @if ($draws !== [])
                    <div class="flex flex-col gap-3 border-t border-line-soft pt-3">
                        <h3 class="text-label uppercase text-ink-3">{{ __('tournament::admin.draws.running') }}</h3>
                        <p class="text-body-s text-ink-2">{{ __('tournament::admin.draws.running_hint') }}</p>
                        @foreach ($draws as $entry)
                            @php
                                $draw = $entry['draw'];
                                $did = (int) $draw->did;
                                $deletable = $draw->discarded && ! $draw->final_seed && $entry['claimed'] === 0;
                            @endphp
                            <div class="flex flex-col gap-2 rounded-md bg-sand p-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-ink">{{ $draw->title }}</span>
                                    <x-ui.badge :tone="$drawTone((string) $draw->status)">{{ $drawBadge((string) $draw->status) }}</x-ui.badge>
                                    @if ($draw->discarded)
                                        <x-ui.badge tone="problem">{{ __('tournament::admin.draws.discarded') }}</x-ui.badge>
                                    @endif
                                </div>
                                <p class="text-body-s text-ink-2">
                                    {{ $drawStatus((string) $draw->status) }} · {{ __('tournament::admin.draws.progress', ['claimed' => $entry['claimed'], 'tokens' => $entry['tokens']]) }}@if ($draw->pick_deadline) · {{ __('tournament::admin.draws.until', ['date' => date('d.m.Y H:i', (int) strtotime($draw->pick_deadline))]) }}@endif
                                </p>
                                <div class="flex flex-wrap items-center gap-3">
                                    @if ($canDraw && ! $draw->discarded)
                                        @if ($draw->status === 'committed')
                                            <form method="POST" action="{{ route('draw.execute', ['did' => $did]) }}" x-on:submit.prevent="ask($el, {{ $ask(__('tournament::admin.draws.execute_confirm'), __('tournament::admin.draws.execute')) }})">
                                                @csrf
                                                <x-ui.button type="submit" variant="dark" :block="false">{{ __('tournament::admin.draws.execute') }}</x-ui.button>
                                            </form>
                                        @elseif ($draw->status === 'executed')
                                            <form method="POST" action="{{ route('draw.open', ['did' => $did]) }}">
                                                @csrf
                                                <x-ui.button type="submit" variant="dark" :block="false">{{ __('tournament::admin.draws.open') }}</x-ui.button>
                                            </form>
                                            <span class="text-body-s text-warn-ink">{{ __('tournament::admin.draws.open_hint') }}</span>
                                        @elseif ($draw->status === 'picking')
                                            <form method="POST" action="{{ route('draw.close', ['did' => $did]) }}" x-on:submit.prevent="ask($el, {{ $ask(__('tournament::admin.draws.close_confirm'), __('tournament::admin.draws.close')) }})">
                                                @csrf
                                                <x-ui.button type="submit" variant="dark" :block="false">{{ __('tournament::admin.draws.close') }}</x-ui.button>
                                            </form>
                                        @endif
                                    @endif
                                    @if ($canDraw && $deletable)
                                        <form method="POST" action="{{ route('draw.delete', ['did' => $did]) }}" x-on:submit.prevent="ask($el, {{ $ask(__('tournament::admin.draws.delete_confirm'), __('tournament::admin.draws.delete')) }})">
                                            @csrf
                                            <x-ui.button type="submit" variant="danger" :block="false">{{ __('tournament::admin.draws.delete') }}</x-ui.button>
                                        </form>
                                    @endif
                                    <a href="{{ route('draw.tokens', ['did' => $did]) }}" class="font-semibold">{{ __('tournament::admin.draws.tokens') }}</a>
                                    <a href="{{ route('draw.record', ['slug' => $draw->slug]) }}" class="font-semibold">{{ __('tournament::admin.draws.record') }}</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($executedDraws !== [])
                    <div class="flex flex-col gap-3 border-t border-line-soft pt-3">
                        <h3 class="text-label uppercase text-ink-3">{{ __('tournament::admin.draws.apply_title') }}</h3>
                        <p class="text-body-s text-ink-2">{{ __('tournament::admin.draws.apply_text') }}</p>
                        @foreach ($executedDraws as $draw)
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-md bg-sand p-3">
                                <span class="flex min-w-0 flex-col">
                                    <span class="font-semibold text-ink">{{ $draw->title }}</span>
                                    <a href="{{ route('draw.record', ['slug' => $draw->slug]) }}" class="text-body-s font-semibold">{{ __('tournament::admin.draws.record') }}</a>
                                </span>
                                @if ($applyUrl = $url('tournament.admin.apply-draw', ['did' => (int) $draw->did]))
                                    <form method="POST" action="{{ $applyUrl }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="dark" :block="false">{{ __('tournament::admin.draws.apply') }}</x-ui.button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-ui.card>

        {{-- Gruppen & KO: group state, bracket state, refresh, bracket pre-draw --}}
        <x-ui.card id="turnierleitung-gruppen" :title="__('tournament::admin.groups.card')">
            <div class="flex flex-col gap-4">
                @foreach ($state as $entry)
                    <div class="flex flex-col gap-2">
                        <h3 class="font-semibold text-ink">{{ $entry['phase']['label'] }} <span class="text-body-s font-normal text-ink-2">({{ $entry['phase']['key'] }})</span></h3>
                        <p class="text-body-s text-ink-2">{{ __('tournament::admin.groups.pool', ['count' => $entry['pool']]) }}</p>
                        <x-ui.table :caption="__('tournament::admin.groups.caption', ['phase' => $entry['phase']['label']])" :stack="false">
                            <x-slot:head>
                                <tr>
                                    <th scope="col">{{ __('tournament::admin.groups.col_group') }}</th>
                                    <th scope="col">{{ __('tournament::admin.groups.col_assigned') }}</th>
                                    <th scope="col">{{ __('tournament::admin.groups.col_matches') }}</th>
                                    <th scope="col">{{ __('tournament::admin.groups.col_status') }}</th>
                                </tr>
                            </x-slot:head>
                            @foreach ($entry['groups'] as $key => $group)
                                <tr>
                                    <td class="font-semibold">{{ __('tournament::admin.groups.group', ['group' => $key]) }}</td>
                                    <td>{{ $group['assigned'] }}/{{ $group['size'] }}</td>
                                    <td>{{ $group['matches'] }}</td>
                                    <td>
                                        @if ($group['complete'] && $group['matches'] > 0)
                                            <x-ui.badge tone="good">{{ __('tournament::admin.groups.ready') }}</x-ui.badge>
                                        @elseif ($group['complete'])
                                            <x-ui.badge tone="attention">{{ __('tournament::admin.groups.matches_missing') }}</x-ui.badge>
                                        @else
                                            <x-ui.badge tone="attention">{{ __('tournament::admin.groups.draw_missing') }}</x-ui.badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    </div>
                @endforeach

                @if ($brackets !== [])
                    <div class="flex flex-col gap-2 border-t border-line-soft pt-3">
                        <h3 class="font-semibold text-ink">{{ __('tournament::admin.groups.brackets') }}</h3>
                        <ul class="flex flex-col gap-1 text-body-s text-ink-2">
                            @foreach ($brackets as $bracket)
                                <li>{{ __('tournament::admin.groups.bracket_line', ['phase' => $bracket['phase']['label'], 'key' => $bracket['phase']['key'], 'lines' => $bracket['lines'], 'expected_lines' => $bracket['expected_lines'], 'matches' => $bracket['matches'], 'expected_matches' => $bracket['expected_matches']]) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($refreshUrl = $url('tournament.admin.refresh-bracket'))
                    <form method="POST" action="{{ $refreshUrl }}" class="flex flex-col gap-2">
                        @csrf
                        <div><x-ui.button type="submit" variant="secondary" :block="false">{{ __('tournament::admin.groups.refresh') }}</x-ui.button></div>
                        <p class="text-body-s text-ink-2">{{ __('tournament::admin.groups.refresh_help') }}</p>
                    </form>
                @endif

                @if ($brackets !== [] && ($bracketUrl = $url('tournament.admin.prepare-bracket-draw')))
                    <div class="flex flex-col gap-3 border-t border-line-soft pt-3">
                        <h3 class="font-semibold text-ink">{{ __('tournament::admin.groups.predraw_title') }}</h3>
                        <p class="text-body-s text-ink-2">{{ __('tournament::admin.groups.predraw_text') }}</p>
                        <p class="text-body-s text-ink-2">{{ $isTest ? __('tournament::admin.groups.predraw_test') : __('tournament::admin.groups.predraw_real') }}</p>
                        @foreach ($brackets as $bracket)
                            @foreach ($bracket['phase']['lines']['pool_codes'] ?? [] as $pool => $code)
                                <form method="POST" action="{{ $bracketUrl }}" class="flex flex-col gap-2 rounded-md bg-sand p-3">
                                    @csrf
                                    <input type="hidden" name="phase" value="{{ $bracket['phase']['key'] }}">
                                    <input type="hidden" name="pool" value="{{ $pool }}">
                                    <p class="font-semibold text-ink">{{ __('tournament::admin.groups.predraw_pool', ['phase' => $bracket['phase']['label'], 'pool' => strtoupper((string) $pool)]) }}</p>
                                    @unless ($isTest)
                                        <x-ui.field for="tl-bracket-lead-{{ $loop->parent->index }}-{{ $loop->index }}" :label="__('tournament::admin.groups.predraw_lead')">
                                            <x-ui.select name="lead_minutes" id="tl-bracket-lead-{{ $loop->parent->index }}-{{ $loop->index }}" :options="$bracketLeadOptions" selected="2" />
                                        </x-ui.field>
                                    @endunless
                                    <div><x-ui.button type="submit" variant="secondary" :block="false">{{ __('tournament::admin.groups.predraw_submit') }}</x-ui.button></div>
                                </form>
                            @endforeach
                        @endforeach
                    </div>
                @endif
            </div>
        </x-ui.card>

        {{-- Ehepaare & Losentscheid --}}
        <x-ui.card id="turnierleitung-paare" :title="__('tournament::admin.couples.card')">
            <div class="flex flex-col gap-4">
                <p class="text-body-s text-ink-2">{{ __('tournament::admin.couples.text') }}</p>
                <p class="text-body-s text-ink-2">{{ __('tournament::admin.couples.hint') }}</p>

                @if ($couples !== [])
                    <ul class="flex flex-col gap-2">
                        @foreach ($couples as [$a, $b])
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-sand px-3 py-2">
                                <span class="text-ink">
                                    {{ $a['label'] }} –
                                    @if ($b !== null)
                                        {{ $b['label'] }}
                                    @else
                                        <strong>{{ __('tournament::admin.couples.dangling') }}</strong> {{ __('tournament::admin.couples.dangling_text', ['tpid' => (int) $a['partner_tpid']]) }}
                                    @endif
                                </span>
                                @if ($partnerUrl = $url('tournament.admin.partner'))
                                    <form method="POST" action="{{ $partnerUrl }}">
                                        @csrf
                                        <input type="hidden" name="tpid_a" value="{{ (int) $a['tpid'] }}">
                                        <x-ui.button type="submit" variant="secondary" :block="false">{{ __('tournament::admin.couples.unlink') }}</x-ui.button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-ink-2">{{ __('tournament::admin.couples.none') }}</p>
                @endif

                @if ($partnerUrl = $url('tournament.admin.partner'))
                    <form method="POST" action="{{ $partnerUrl }}" class="flex flex-col gap-3">
                        @csrf
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.field for="tl-partner-a" :label="__('tournament::admin.couples.player')">
                                <x-ui.select name="tpid_a" id="tl-partner-a" required :options="$playerOptions" :placeholder="__('tournament::admin.couples.choose')" />
                            </x-ui.field>
                            <x-ui.field for="tl-partner-b" :label="__('tournament::admin.couples.partner')">
                                <x-ui.select name="tpid_b" id="tl-partner-b" required :options="$playerOptions" :placeholder="__('tournament::admin.couples.choose')" />
                            </x-ui.field>
                        </div>
                        <div><x-ui.button type="submit" variant="dark" :block="false">{{ __('tournament::admin.couples.submit') }}</x-ui.button></div>
                    </form>
                @endif

                <p class="text-body-s text-ink-2">
                    {{ __('tournament::admin.couples.comments') }}
                    <a href="{{ route('tournament.players') }}" class="font-semibold">{{ __('tournament::admin.couples.comments_link') }}</a>.
                </p>

                <div id="turnierleitung-losentscheid" class="flex flex-col gap-3 border-t border-line-soft pt-3">
                    <h3 class="font-display text-display-m text-ink">{{ __('tournament::admin.lot.title') }}</h3>
                    <p class="text-body-s text-ink-2">{{ __('tournament::admin.lot.text') }}</p>
                    <p class="text-body-s text-ink-2">{{ __('tournament::admin.lot.formula') }}</p>

                    @foreach ($openLots as $lot)
                        <x-ui.notice tone="attention" :title="__('tournament::admin.lot.open_title', ['title' => $lot['title']])">
                            <ul class="flex flex-col gap-0.5">
                                @foreach ($lot['players'] as $p)
                                    <li>{{ __('tournament::admin.lot.open_player', ['label' => $p['label'], 'tpid' => $p['tpid']]) }}</li>
                                @endforeach
                            </ul>
                            <p class="mt-1 font-mono text-body-s">{{ $lot['scope'] }}</p>
                        </x-ui.notice>
                    @endforeach

                    @if ($lotDecisions !== [])
                        <p class="font-semibold text-ink">{{ __('tournament::admin.lot.decided') }}</p>
                        <ul class="flex flex-col gap-2">
                            @foreach ($lotDecisions as $decision)
                                <li class="flex flex-col">
                                    <span class="text-ink">{{ $decision['title'] }}: <strong>{{ implode(__('tournament::admin.lot.before'), $decision['order']) }}</strong></span>
                                    <span class="font-mono text-body-s text-ink-2">{{ $decision['scope'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($openLots === [])
                        <p class="text-ink-2">{{ __('tournament::admin.lot.none') }}</p>
                    @endif

                    @if ($lotUrl = $url('tournament.admin.lot'))
                        <details class="group" @if ($openLots !== []) open @endif>
                            <summary class="flex min-h-12 cursor-pointer list-none items-center gap-2 font-semibold text-ink">
                                <x-ui.icon name="chevron-down" class="shrink-0 transition-transform group-open:rotate-180" />{{ __('tournament::admin.lot.manual') }}
                            </summary>
                            <div class="mt-2 flex flex-col gap-3">
                                <p class="text-body-s text-ink-2">{{ __('tournament::admin.lot.manual_text') }}</p>
                                @if ($lotOrders !== [])
                                    <p class="text-body-s font-semibold text-ink">{{ __('tournament::admin.lot.manual_set') }}</p>
                                    <ul class="flex flex-col gap-0.5 text-body-s">
                                        @foreach ($lotOrders as $scope => $order)
                                            <li><span class="font-mono">{{ $scope }}</span>: {{ is_array($order) ? implode(', ', array_map('strval', array_filter($order, 'is_scalar'))) : '' }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <form method="POST" action="{{ $lotUrl }}" class="flex flex-col gap-3">
                                    @csrf
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <x-ui.field for="tl-lot-scope" :label="__('tournament::admin.lot.scope')">
                                            <x-ui.input name="scope" id="tl-lot-scope" required maxlength="64" :value="$openLots[0]['scope'] ?? ''" placeholder="phase:DG:place:2" />
                                        </x-ui.field>
                                        <x-ui.field for="tl-lot-order" :label="__('tournament::admin.lot.order')">
                                            <x-ui.input name="order" id="tl-lot-order" required maxlength="255" placeholder="17, 12" />
                                        </x-ui.field>
                                    </div>
                                    <div><x-ui.button type="submit" variant="dark" :block="false">{{ __('tournament::admin.lot.submit') }}</x-ui.button></div>
                                </form>
                            </div>
                        </details>
                    @endif
                </div>
            </div>
        </x-ui.card>

        {{-- Testmodus & Zurücksetzen --}}
        <x-ui.card id="turnierleitung-test" :title="__('tournament::admin.test.card')">
            <div class="flex flex-col gap-4">
                @if ($isTest)
                    <div class="flex flex-col gap-2">
                        <h3 class="font-semibold text-ink">{{ __('tournament::admin.test.results_title') }}</h3>
                        <p class="text-body-s text-ink-2">{{ __('tournament::admin.test.results_text') }}</p>
                        <p class="text-body-s text-ink-2">{{ __('tournament::admin.test.results_hint') }}</p>
                        @if ($resultsUrl = $url('tournament.admin.simulate-results'))
                            <form method="POST" action="{{ $resultsUrl }}" x-on:submit.prevent="ask($el, {{ $ask(__('tournament::admin.test.results_confirm'), __('tournament::admin.test.results_submit')) }})">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" :block="false">{{ __('tournament::admin.test.results_submit') }}</x-ui.button>
                            </form>
                        @endif
                    </div>
                @endif

                <p class="text-body-s text-ink-2">{{ __('tournament::admin.test.simulate_text') }}</p>
                <p class="text-body-s text-ink-2">{{ __('tournament::admin.test.simulate_hint') }}</p>

                @if ($simulateUrl = $url('tournament.admin.simulate'))
                    <form method="POST" action="{{ $simulateUrl }}">
                        @csrf
                        <input type="hidden" name="mode" value="member">
                        <x-ui.button type="submit" variant="secondary" :block="false">{{ __('tournament::admin.test.as_member') }}</x-ui.button>
                    </form>
                    @if ($simulateOptions !== [])
                        <form method="POST" action="{{ $simulateUrl }}" class="flex flex-col gap-2.5">
                            @csrf
                            <input type="hidden" name="mode" value="player">
                            <x-ui.field for="tl-simulate" :label="__('tournament::admin.test.as_player')">
                                <x-ui.select name="tpid" id="tl-simulate" :options="$simulateOptions" />
                            </x-ui.field>
                            <div><x-ui.button type="submit" variant="secondary" :block="false">{{ __('tournament::admin.test.take_view') }}</x-ui.button></div>
                        </form>
                    @endif
                    <form method="POST" action="{{ $simulateUrl }}">
                        @csrf
                        <input type="hidden" name="mode" value="off">
                        <x-ui.button type="submit" variant="text" :block="false">{{ __('tournament::admin.test.stop') }}</x-ui.button>
                    </form>
                @endif

                <div class="flex flex-col gap-3 border-t border-line-soft pt-3">
                    <h3 class="font-display text-display-m text-danger">{{ __('tournament::admin.reset.title') }}</h3>
                    <p class="text-body-s text-ink-2">{{ __('tournament::admin.reset.text') }}</p>
                    @if ($isTest)
                        <p class="text-body-s text-ink-2">{{ __('tournament::admin.reset.test_text') }}</p>
                    @else
                        <x-ui.notice tone="problem" :title="__('tournament::admin.reset.real_title')">{{ __('tournament::admin.reset.real_text') }}</x-ui.notice>
                    @endif

                    @if ($resetUrl = $url('tournament.admin.reset'))
                        <form method="POST" action="{{ $resetUrl }}" class="flex flex-col gap-3" x-on:submit.prevent="ask($el, {{ $ask(__('tournament::admin.reset.confirm'), __('tournament::admin.reset.submit')) }})">
                            @csrf
                            <fieldset class="flex flex-col gap-2">
                                <legend class="sr-only">{{ __('tournament::admin.reset.scope') }}</legend>
                                <label class="flex items-center gap-2 text-ink"><input type="radio" name="scope" value="results" checked class="size-5 accent-clay"> {{ __('tournament::admin.reset.results') }}</label>
                                <label class="flex items-center gap-2 text-ink"><input type="radio" name="scope" value="draw" class="size-5 accent-clay"> {{ __('tournament::admin.reset.draw') }}</label>
                                <label class="flex items-center gap-2 text-ink"><input type="radio" name="scope" value="all" class="size-5 accent-clay"> {{ __('tournament::admin.reset.all') }}</label>
                            </fieldset>
                            @unless ($isTest)
                                <x-ui.field for="tl-reset-confirm" :label="__('tournament::admin.reset.confirm_title')">
                                    <x-ui.input name="confirm" id="tl-reset-confirm" maxlength="160" :placeholder="$tournament->title" />
                                </x-ui.field>
                                <x-ui.field for="tl-reset-reason" :label="__('tournament::admin.reset.reason')">
                                    <x-ui.input name="reason" id="tl-reset-reason" maxlength="160" :placeholder="__('tournament::admin.reset.reason_placeholder')" />
                                </x-ui.field>
                            @endunless
                            <x-ui.button type="submit" variant="danger">{{ __('tournament::admin.reset.submit') }}</x-ui.button>
                        </form>
                    @endif
                </div>
            </div>
        </x-ui.card>
    </div>

    <x-ui.modal id="turnierleitung-confirm" show="pending !== null" close="pending = null" :label="__('tournament::admin.index.confirm_title')">
        <x-slot:title>{{ __('tournament::admin.index.confirm_title') }}</x-slot:title>
        <p class="text-ink" x-text="pending ? pending.text : ''"></p>
        <x-slot:footer>
            <x-ui.button variant="secondary" :block="false" x-on:click="pending = null">{{ __('tournament::admin.index.cancel') }}</x-ui.button>
            <x-ui.button :block="false" data-modal-initial x-on:click="confirmPending()"><span x-text="pending ? pending.submit : ''"></span></x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
@endsection
