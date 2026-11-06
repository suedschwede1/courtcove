{{--
    Draws admin (old view/draw/admin/index.phtml), layout from the Admin-Auslosungen mockup: page head, the
    "Bisherige Ziehungen" table (title with deadline/reason/beacon line, mode, status badges, record and tokens
    links, next step) and the manual commit form collapsed below it. Every next-step action is a POST form that
    opens one shared confirm modal instead of the old inline confirm() dialogs.
    Needs: $draws, $suggestedRound, $beaconError, $commitErrors.
--}}
@extends('tournament-base::layouts.area')

@section('admin-title', __('draw::admin.index.title'))

@section('admin-content')
@php
    use App\Modules\Draw\Support\DrawNextStep;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Js;

    $statusLabel = static fn (string $status): string => match ($status) {
        'picking' => __('draw::admin.index.status_picking'),
        'closed' => __('draw::admin.index.status_closed'),
        'executed' => __('draw::admin.index.status_executed'),
        'committed' => __('draw::admin.index.status_committed'),
        default => $status, // unknown value: shown raw (ZF2), so database drift is not disguised
    };
    $formOpen = $commitErrors !== [] || old('title') !== null;
    $hasRoute = static fn (string $name): bool => Route::has($name);
@endphp
<div class="flex flex-col gap-5" x-data="{ pending: null }">
    <div class="flex flex-col gap-1.5">
        <p class="text-label uppercase text-ink-3">{{ __('draw::admin.index.eyebrow') }}</p>
        <h1 class="font-display text-display-l text-ink md:text-display-xl">{{ __('draw::admin.index.title') }}</h1>
        <p class="max-w-3xl text-ink-2">{{ __('draw::admin.index.lead') }}</p>
    </div>

    @include('tournament-base::partials.flash')

    @if ($commitErrors !== [])
        <x-ui.notice tone="problem" :title="__('draw::admin.index.errors_title')">
            <ul class="flex flex-col gap-1">
                @foreach ($commitErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.notice>
    @endif

    <x-ui.card padding="none" aria-labelledby="draw-admin-list">
        <h2 id="draw-admin-list" class="border-b border-line-soft px-4 py-3.5 font-display text-display-m text-ink md:px-5">{{ __('draw::admin.index.list_title') }}</h2>

        @if ($draws->isEmpty())
            <p class="p-4 text-ink-2 md:p-5">{{ __('draw::admin.index.empty') }}</p>
        @else
            <x-ui.table :caption="__('draw::admin.index.caption')">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('draw::admin.index.col_title') }}</th>
                        <th scope="col">{{ __('draw::admin.index.col_mode') }}</th>
                        <th scope="col">{{ __('draw::admin.index.col_status') }}</th>
                        <th scope="col">{{ __('draw::admin.index.col_record') }}</th>
                        <th scope="col" class="text-right">{{ __('draw::admin.index.col_next') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($draws as $draw)
                    @php
                        $did = (int) $draw->did;
                        $step = DrawNextStep::for($draw);
                        $sub = array_filter([
                            $draw->pick_deadline ? __('draw::admin.index.deadline', ['date' => date('d.m. H:i', (int) strtotime($draw->pick_deadline))]) : null,
                            $draw->discarded ? $draw->discarded_reason : null,
                            $draw->beacon_source,
                        ]);
                        $confirm = static fn (string $route, string $text, string $submit, ?string $deadline = null): string => (string) Js::from([
                            'url' => route($route, ['did' => $did]),
                            'text' => $text,
                            'submit' => $submit,
                            'deadline' => $deadline,
                        ]);
                    @endphp
                    <tr>
                        <td class="ds-cell--title">
                            <span class="flex min-w-0 flex-col">
                                <span class="font-bold text-ink">{{ $draw->title }}</span>
                                @foreach ($sub as $line)
                                    <span class="break-words text-body-s font-normal text-ink-2">{{ $line }}</span>
                                @endforeach
                            </span>
                        </td>
                        <td data-label="{{ __('draw::admin.index.col_mode') }}">{{ $draw->mode === 'token' ? __('draw::admin.index.mode_token') : __('draw::admin.index.mode_direct') }}</td>
                        <td data-label="{{ __('draw::admin.index.col_status') }}">
                            <span class="flex flex-wrap items-center gap-1.5">
                                <x-ui.badge :tone="DrawNextStep::tone($draw)">{{ $statusLabel((string) $draw->status) }}</x-ui.badge>
                                @if ($draw->discarded)
                                    <x-ui.badge tone="problem">{{ __('draw::admin.index.discarded') }}</x-ui.badge>
                                @endif
                            </span>
                        </td>
                        <td data-label="{{ __('draw::admin.index.col_record') }}">
                            <span class="flex flex-wrap items-center gap-3">
                                <a href="{{ route('draw.record', ['slug' => $draw->slug]) }}" class="font-semibold">{{ __('draw::admin.index.record_link') }}</a>
                                @if ($draw->mode === 'token' && $hasRoute('draw.tokens'))
                                    <a href="{{ route('draw.tokens', ['did' => $did]) }}" class="font-semibold">{{ __('draw::admin.index.tokens_link') }}</a>
                                @endif
                            </span>
                        </td>
                        <td>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                @if ($step === 'execute')
                                    <x-ui.button variant="dark" :block="false" class="whitespace-nowrap" x-on:click="pending = {{ $confirm('draw.execute', __('draw::admin.index.execute_confirm'), __('draw::admin.index.execute')) }}">{{ __('draw::admin.index.execute') }}</x-ui.button>
                                @elseif ($step === 'open')
                                    <x-ui.button variant="dark" :block="false" class="whitespace-nowrap" x-on:click="pending = {{ $confirm('draw.open', __('draw::admin.index.open_confirm'), __('draw::admin.index.open'), $draw->pick_deadline ? str_replace(' ', 'T', substr($draw->pick_deadline, 0, 16)) : '') }}">{{ __('draw::admin.index.open') }}</x-ui.button>
                                @elseif ($step === 'close' && $hasRoute('draw.close'))
                                    <x-ui.button variant="dark" :block="false" class="whitespace-nowrap" x-on:click="pending = {{ $confirm('draw.close', __('draw::admin.index.close_confirm'), __('draw::admin.index.close')) }}">{{ __('draw::admin.index.close') }}</x-ui.button>
                                @elseif ($step === 'apply')
                                    @if ($hasRoute('tournament.admin'))
                                        <x-ui.button variant="secondary" :block="false" class="whitespace-nowrap" :href="route('tournament.admin')">{{ __('draw::admin.index.apply_hint') }}</x-ui.button>
                                    @else
                                        <span class="text-body-s text-ink-2">{{ __('draw::admin.index.apply_hint') }}</span>
                                    @endif
                                @elseif ($step === 'none')
                                    <span class="text-body-s text-ink-2">{{ __('draw::admin.index.no_action') }}</span>
                                @endif

                                @if (DrawNextStep::canDelete($draw))
                                    <x-ui.button variant="danger" :block="false" class="whitespace-nowrap" x-on:click="pending = {{ $confirm('draw.delete', __('draw::admin.index.delete_confirm'), __('draw::admin.index.delete')) }}">{{ __('draw::admin.index.delete') }}</x-ui.button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.disclosure :summary="__('draw::admin.form.summary')" :open="$formOpen">
        <div class="flex flex-col gap-4">
            <p class="text-body-s text-ink-2">{{ __('draw::admin.form.intro') }}</p>

            @if ($beaconError !== null)
                <x-ui.notice tone="attention">{{ __('draw::admin.form.beacon_error', ['message' => $beaconError]) }}</x-ui.notice>
            @endif

            <form method="POST" action="{{ route('draw.commit') }}" class="flex flex-col gap-4">
                @csrf
                <div class="grid gap-3 md:grid-cols-2">
                    <x-ui.field for="draw-title" :label="__('draw::admin.form.title')">
                        <x-ui.input name="title" id="draw-title" required maxlength="160" :placeholder="__('draw::admin.form.title_placeholder')" />
                    </x-ui.field>
                    <x-ui.field for="draw-round" :label="__('draw::admin.form.round')" :help="__('draw::admin.form.round_help')">
                        <x-ui.input type="number" name="round" id="draw-round" min="1" required :value="old('round', $suggestedRound !== null ? (string) $suggestedRound : '')" />
                    </x-ui.field>
                    <x-ui.field for="draw-entries" :label="__('draw::admin.form.entries')" :help="__('draw::admin.form.entries_help')">
                        <x-ui.textarea name="entries" id="draw-entries" rows="6" required placeholder="101&#10;102&#10;103&#10;104" />
                    </x-ui.field>
                    <x-ui.field for="draw-slots" :label="__('draw::admin.form.slots')" :help="__('draw::admin.form.slots_help')">
                        <x-ui.textarea name="slots" id="draw-slots" rows="6" required placeholder="A2&#10;B2&#10;A3&#10;B3" />
                    </x-ui.field>
                </div>
                <x-ui.field for="draw-rule" :label="__('draw::admin.form.rule')" :help="__('draw::admin.form.rule_help')">
                    <x-ui.textarea name="rule" id="draw-rule" rows="3" required :placeholder="__('draw::admin.form.rule_placeholder')" />
                </x-ui.field>
                <div class="grid gap-3 md:grid-cols-2">
                    <x-ui.field for="draw-tid" :label="__('draw::admin.form.tid')">
                        <x-ui.input type="number" name="tid" id="draw-tid" min="1" />
                    </x-ui.field>
                </div>
                <div>
                    <x-ui.button type="submit" :block="false">{{ __('draw::admin.form.submit') }}</x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.disclosure>

    <x-ui.modal id="draw-confirm" show="pending !== null" close="pending = null" :label="__('draw::admin.index.confirm_title')">
        <x-slot:title>{{ __('draw::admin.index.confirm_title') }}</x-slot:title>
        <form method="POST" id="draw-confirm-form" x-bind:action="pending ? pending.url : ''" class="flex flex-col gap-4">
            @csrf
            <p class="text-ink" x-text="pending ? pending.text : ''"></p>
            <template x-if="pending && pending.deadline !== null">
                <x-ui.field for="draw-deadline" :label="__('draw::admin.index.deadline_label')">
                    <x-ui.input type="datetime-local" name="deadline" id="draw-deadline" x-bind:value="pending.deadline" />
                </x-ui.field>
            </template>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" :block="false" x-on:click="pending = null">{{ __('draw::admin.index.cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="draw-confirm-form" :block="false" data-modal-initial><span x-text="pending ? pending.submit : ''"></span></x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
@endsection
