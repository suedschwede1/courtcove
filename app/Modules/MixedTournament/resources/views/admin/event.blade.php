{{--
    Participants tab of the event administration (old admin/event.phtml), left column of Admin-Mixed-Event:
    table Name / D/H / ITN / Alter / 2×? / Aktion with an inline row edit, the bulk actions and the add form
    with the member picker (searches the roster on demand instead of embedding it).
    Every participant form carries the hidden event_id: the controller rejects a participant of another event.
--}}
@extends('mixed::admin.layout')

@section('title', $event->title)

@section('mixed-admin-content')
@php
    use App\Modules\MixedTournament\Support\MixedFormat;

    $eventId = (int) $event->id;
    $genderOptions = ['' => __('mixed::admin.participants.gender_none'), 'f' => __('mixed::common.gender_f'), 'm' => __('mixed::common.gender_m')];
@endphp
@include('mixed::admin.partials.event-head')

@include('mixed::partials.tabs', ['event' => $event, 'active' => 'teilnehmer', 'adminView' => true, 'canAdmin' => true])

<x-ui.card padding="none" class="ds-admin-rows" :title="__('mixed::admin.participants.title').' '.__('mixed::admin.participants.count', ['active' => $activeCount, 'total' => $totalCount])">
    {{-- Bulk actions as a toolbar row (in the card header they squeeze the title on phones). --}}
    <div class="mb-4 mt-4 flex flex-wrap gap-2.5 md:px-5">
        <form method="POST" action="{{ route('mixed.admin.match-members', ['event' => $eventId]) }}">
            @csrf
            <x-ui.button type="submit" variant="secondary" :block="false">{{ __('mixed::admin.participants.match_members') }}</x-ui.button>
        </form>
        @if ($participants->isNotEmpty())
            <form method="POST" action="{{ route('mixed.admin.pause-all', ['event' => $eventId]) }}" x-data x-on:submit="if (! confirm(@js(__('mixed::admin.participants.pause_all_confirm')))) $event.preventDefault()">
                @csrf
                <x-ui.button type="submit" variant="secondary" :block="false">{{ __('mixed::admin.participants.pause_all') }}</x-ui.button>
            </form>
        @endif
    </div>

    @if ($participants->isEmpty())
        <div class="p-4">
            <x-ui.notice tone="neutral">{{ __('mixed::admin.participants.none') }}</x-ui.notice>
        </div>
    @else
        <x-ui.table :caption="__('mixed::admin.participants.title')">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('mixed::admin.participants.col_name') }}</th>
                    <th scope="col">{{ __('mixed::admin.participants.col_gender') }}</th>
                    <th scope="col">{{ __('mixed::admin.participants.col_itn') }}</th>
                    <th scope="col">{{ __('mixed::admin.participants.col_age') }}</th>
                    <th scope="col"><abbr title="{{ __('mixed::admin.participants.col_no_extra_title') }}">{{ __('mixed::admin.participants.col_no_extra') }}</abbr></th>
                    <th scope="col" class="text-right">{{ __('mixed::admin.participants.col_action') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($participants as $participant)
                @php
                    $pid = (int) $participant->id;
                    $form = 'mixed-edit-'.$pid;
                    $paused = $participant->status === 'paused';
                @endphp
                <tr x-data="{ editing: false }">
                    <td class="ds-cell--title" data-label="{{ __('mixed::admin.participants.col_name') }}">
                        <span x-show="! editing" class="inline-flex flex-wrap items-center gap-2">
                            <span>{{ $participant->label }}</span>
                            @if ($paused)
                                <x-ui.badge tone="neutral">{{ __('mixed::admin.participants.paused') }}</x-ui.badge>
                            @endif
                        </span>
                        <span x-show="editing" x-cloak class="block w-full">
                            <x-ui.input name="label" id="mixed-edit-label-{{ $pid }}" :value="$participant->label" form="{{ $form }}" required maxlength="120" autocomplete="off" aria-label="{{ __('mixed::admin.participants.col_name') }}" />
                        </span>
                    </td>
                    <td @if ($participant->gender !== null) data-label="{{ __('mixed::admin.participants.col_gender') }}" @endif>
                        <span x-show="! editing">{{ MixedFormat::gender($participant->gender) }}</span>
                        <span x-show="editing" x-cloak class="block w-24">
                            <x-ui.select name="gender" id="mixed-edit-gender-{{ $pid }}" :options="$genderOptions" :selected="(string) $participant->gender" form="{{ $form }}" aria-label="{{ __('mixed::admin.participants.field_gender') }}" />
                        </span>
                    </td>
                    <td class="tabular-nums" @if ($participant->itn !== null) data-label="{{ __('mixed::admin.participants.col_itn') }}" @endif>
                        <span x-show="! editing">{{ MixedFormat::itn($participant->itn) }}</span>
                        <span x-show="editing" x-cloak class="block w-24">
                            <x-ui.input type="number" name="itn" id="mixed-edit-itn-{{ $pid }}" :value="$participant->itn !== null ? (string) (float) $participant->itn : ''" step="0.1" min="0" max="99.9" inputmode="decimal" form="{{ $form }}" aria-label="{{ __('mixed::admin.participants.field_itn') }}" />
                        </span>
                    </td>
                    <td class="tabular-nums" @if ($participant->age !== null) data-label="{{ __('mixed::admin.participants.col_age') }}" @endif>
                        <span x-show="! editing">{{ $participant->age ?? '' }}</span>
                        <span x-show="editing" x-cloak class="block w-24">
                            <x-ui.input type="number" name="age" id="mixed-edit-age-{{ $pid }}" :value="$participant->age !== null ? (string) $participant->age : ''" min="0" max="120" inputmode="numeric" form="{{ $form }}" aria-label="{{ __('mixed::admin.participants.field_age') }}" />
                        </span>
                    </td>
                    <td @if ($participant->no_extra_match) data-label="{{ __('mixed::admin.participants.col_no_extra_title') }}" @endif>
                        <span x-show="! editing">
                            @if ($participant->no_extra_match)
                                <x-ui.icon name="check" class="text-clay" :label="__('mixed::admin.participants.no_extra_yes')" />
                            @else
                                <span class="sr-only">{{ __('mixed::admin.participants.no_extra_no') }}</span>
                            @endif
                        </span>
                        <label x-show="editing" x-cloak class="inline-flex min-h-touch-min items-center gap-2">
                            <input type="checkbox" name="no_extra_match" value="1" form="{{ $form }}" class="size-5 accent-clay" @checked($participant->no_extra_match)>
                            <span class="sr-only">{{ __('mixed::admin.participants.no_extra_match') }}</span>
                        </label>
                    </td>
                    <td>
                        <div class="flex flex-wrap justify-end gap-2 lg:flex-nowrap">
                            <form id="{{ $form }}" method="POST" action="{{ route('mixed.admin.update-participant', ['participant' => $pid]) }}" class="contents">
                                @csrf
                                <input type="hidden" name="event_id" value="{{ $eventId }}">
                                <span x-show="editing" x-cloak class="contents">
                                    <x-ui.button type="submit" :block="false">{{ __('mixed::admin.participants.save') }}</x-ui.button>
                                    <x-ui.button variant="secondary" :block="false" x-on:click="editing = false">{{ __('mixed::admin.participants.cancel') }}</x-ui.button>
                                </span>
                            </form>
                            <span x-show="! editing" class="contents">
                                <x-ui.button variant="secondary" :block="false" x-on:click="editing = true; $nextTick(() => document.getElementById('mixed-edit-label-{{ $pid }}').focus())" aria-label="{{ __('mixed::admin.participants.edit_label', ['name' => $participant->label]) }}">{{ __('mixed::admin.participants.edit') }}</x-ui.button>
                                <form method="POST" action="{{ route('mixed.admin.participant-status', ['participant' => $pid]) }}">
                                    @csrf
                                    <input type="hidden" name="event_id" value="{{ $eventId }}">
                                    <input type="hidden" name="status" value="{{ $paused ? 'active' : 'paused' }}">
                                    <x-ui.button type="submit" variant="secondary" :block="false">{{ $paused ? __('mixed::admin.participants.activate') : __('mixed::admin.participants.pause') }}</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('mixed.admin.remove-participant', ['participant' => $pid]) }}" x-on:submit="if (! confirm(@js(__('mixed::admin.participants.remove_confirm')))) $event.preventDefault()">
                                    @csrf
                                    <input type="hidden" name="event_id" value="{{ $eventId }}">
                                    <x-ui.icon-button type="submit" icon="close" :label="__('mixed::admin.participants.remove_label', ['name' => $participant->label])" class="text-danger" />
                                </form>
                            </span>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-ui.card>

<x-ui.card :title="__('mixed::admin.participants.add_title')" id="mixed-admin-add">
    <p class="mb-4 text-body-s text-ink-2">{{ __('mixed::admin.participants.add_hint') }}</p>
    <form method="POST" action="{{ route('mixed.admin.add-participant', ['event' => $eventId]) }}" class="flex flex-col gap-4" x-data="{ label: '', gender: '', itn: '', age: '' }">
        @csrf
        <div class="grid items-start gap-3 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]">
            @include('mixed::partials.member-picker', ['event' => $event])
            <x-ui.field for="mixed-add-gender" :label="__('mixed::admin.participants.field_gender')">
                <x-ui.select name="gender" id="mixed-add-gender" :options="['' => __('mixed::admin.participants.gender_none'), 'f' => __('mixed::admin.participants.gender_f'), 'm' => __('mixed::admin.participants.gender_m')]" x-model="gender" />
            </x-ui.field>
            <x-ui.field for="mixed-add-itn" :label="__('mixed::admin.participants.field_itn')">
                <x-ui.input type="number" name="itn" id="mixed-add-itn" :value="''" step="0.1" min="0" max="99.9" inputmode="decimal" x-model="itn" />
            </x-ui.field>
            <x-ui.field for="mixed-add-age" :label="__('mixed::admin.participants.field_age')">
                <x-ui.input type="number" name="age" id="mixed-add-age" :value="''" min="0" max="120" inputmode="numeric" x-model="age" />
            </x-ui.field>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="inline-flex min-h-touch-min items-center gap-2.5 font-semibold text-ink">
                <input type="checkbox" name="no_extra_match" value="1" class="size-5 accent-clay">
                <span>{{ __('mixed::admin.participants.no_extra_match') }}</span>
            </label>
            <x-ui.button type="submit" variant="dark" :block="false">{{ __('mixed::admin.participants.add') }}</x-ui.button>
        </div>
    </form>
</x-ui.card>
@endsection
