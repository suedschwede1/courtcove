@extends('members::layout')
@section('members-title', $member->fullName())
@section('members-content')
@php
    use App\Modules\Members\Support\MembersFormat;
    use App\Modules\Members\Support\StatusTone;

    $dueLabels = [
        'ausstehend' => __('members::admin.dues.status_ausstehend'),
        'bezahlt' => __('members::admin.dues.status_bezahlt'),
        'ueberfaellig' => __('members::admin.dues.status_ueberfaellig'),
    ];
    $composeOpen = $errors->hasAny(['member_id', 'fiscal_year_id', 'teilbetrag', 'kommentar']);
    // Details rows (Bezahlt bis, Teilbetrag, Kommentar) start open where they hold something.
    $openDues = $dues->reject(fn ($due) => $due->fiscalYear->isClosed())
        ->mapWithKeys(fn ($due) => [(string) $due->id => $due->teilbetrag !== null || ($due->kommentar ?? '') !== '' || $due->bezahlt_bis_fiscal_year_id !== null])
        ->all();
@endphp
<x-members::page-header :title="$member->fullName()">
    <x-slot:actions>
        <x-ui.button variant="secondary" :block="false" :href="route('members.index')">{{ __('members::admin.common.cancel') }}</x-ui.button>
        <x-ui.button type="submit" :block="false" form="member-edit-form" class="mbr-phone-hidden">{{ __('members::admin.common.save') }}</x-ui.button>
    </x-slot:actions>
    <x-slot:phoneAction>
        <x-ui.button type="submit" form="member-edit-form">{{ __('members::admin.common.save') }}</x-ui.button>
    </x-slot:phoneAction>
</x-members::page-header>

<div class="mbr-detail">
<div class="mbr-detail__main">
<form id="member-edit-form" method="POST" action="{{ route('members.update', $member) }}" class="ui-form-shell">
    @method('PUT')
    @include('members::members._form', ['member' => $member])
</form>

<x-ui.card class="mbr-dues-card" padding="none" :title="__('members::admin.dues.title')" x-data="{ composing: {{ $composeOpen ? 'true' : 'false' }}, openDues: {{ json_encode((object) $openDues, JSON_THROW_ON_ERROR) }} }">
    <x-slot:actions>
        <x-ui.button variant="secondary" :block="false" icon="plus" aria-controls="md-compose" x-bind:aria-expanded="composing.toString()" x-on:click="composing = !composing">{{ __('members::admin.dues.new') }}</x-ui.button>
    </x-slot:actions>
    <form id="md-compose" x-show="composing" @unless($composeOpen) x-cloak @endunless method="POST" action="{{ route('members.dues.store') }}" class="mbr-filters mbr-compose">
        @csrf
        <input type="hidden" name="member_id" value="{{ $member->id }}">
        <input type="hidden" name="fee_category_id" value="{{ $member->fee_category_id }}">
        <x-ui.field class="mbr-filters__field" :label="__('members::admin.members.fee_category')" for="md-fee-category">
            <x-ui.input name="md-fee-category" :value="$member->feeCategory->name ?? '—'" disabled />
        </x-ui.field>
        <x-ui.field class="mbr-filters__field" :label="__('members::admin.dues.fiscal_year')" for="md-fiscal-year" :error="$errors->first('fiscal_year_id') ?: $errors->first('member_id')">
            {{-- Preselect the current business year (old() wins after a validation error) instead of the oldest one. --}}
            <x-ui.select name="fiscal_year_id" :options="$fiscalYears->reject->isClosed()->pluck('name', 'id')->all()" :selected="\App\Support\Ui\FormValue::string(old('fiscal_year_id',\App\Modules\Members\Models\FiscalYear::defaultFor($fiscalYears)?->id))" />
        </x-ui.field>
        <x-ui.field class="mbr-filters__field" :label="__('members::admin.dues.teilbetrag')" for="md-teilbetrag" :error="$errors->first('teilbetrag')">
            <x-ui.input type="number" name="teilbetrag" step="0.01" min="0" />
        </x-ui.field>
        <x-ui.field class="mbr-filters__search" :label="__('members::admin.dues.kommentar')" for="md-kommentar" :error="$errors->first('kommentar')">
            <x-ui.input name="kommentar" />
        </x-ui.field>
        <x-ui.button type="submit" :block="false">{{ __('members::admin.common.save') }}</x-ui.button>
    </form>
    {{-- Compact list (Mitglied-Detail mockup): one line per year; Bezahlt bis, Teilbetrag and Kommentar in a details row. --}}
    <x-ui.table class="mbr-dues-table" :stack="false" :caption="__('members::admin.dues.title')">
        <x-slot:head>
            <tr>
                <th scope="col">{{ __('members::admin.dues.year_short') }}</th>
                <th scope="col">{{ __('members::admin.dues.category_short') }}</th>
                <th scope="col" class="mbr-due-amount">{{ __('members::admin.dues.amount') }}</th>
                <th scope="col">{{ __('members::admin.dues.status') }}</th>
                <th scope="col">{{ __('members::admin.dues.bezahlt_am') }}</th>
                <th scope="col"><span class="sr-only">{{ __('members::admin.dues.details') }}</span></th>
            </tr>
        </x-slot:head>
        @forelse($dues as $due)
            @if($due->fiscalYear->isClosed())
                @include('members::dues._locked-row', ['due' => $due, 'context' => 'member'])
            @else
            @php
                $teilbetragDisplay = MembersFormat::optionalMoney($due->teilbetrag);
            @endphp
            <tr class="mbr-due-line">
                <td class="mbr-due-year ds-cell--title" data-label="{{ __('members::admin.dues.year_short') }}">{{ $due->fiscalYear->name }}</td>
                <td class="mbr-due-info" data-label="{{ __('members::admin.dues.category_short') }}">{{ $due->feeCategory->name }}@if($due->status !== 'bezahlt' && $due->faellig_am !== null) · {{ __('members::admin.dues.due_on', ['date' => $due->faellig_am->isoFormat('L')]) }}@endif</td>
                <td class="mbr-due-amount tabular-nums whitespace-nowrap" data-label="{{ __('members::admin.dues.amount') }}" data-amount>{{ MembersFormat::money($due->amount) }}</td>
                <td class="mbr-due-status cursor-pointer"
                    data-field="status"
                    data-label="{{ __('members::admin.dues.status') }}"
                    x-data="{
                        editing: false, error: null, justSaved: false,
                        value: @js($due->status),
                        displayValue: @js($due->displayStatus()),
                        labels: { ausstehend: @js(__('members::admin.dues.status_ausstehend')), bezahlt: @js(__('members::admin.dues.status_bezahlt')), ueberfaellig: @js(__('members::admin.dues.status_ueberfaellig')) },
                        classes: @js(StatusTone::badgeClasses(StatusTone::DUE)),
                        async save() {
                            this.error = null;
                            const result = await window.saveDueField({{ $due->id }}, 'status', this.value);
                            if (result.ok) { this.editing = false; this.value = result.value; this.displayValue = result.value; window.announceDuePaidOn({{ $due->id }}, result.data); this.justSaved = true; setTimeout(() => this.justSaved = false, 1500); }
                            else { this.error = result.message; }
                        }
                    }"
                    @click="if (!editing) editing = true"
                    @click.outside="if (!error) editing = false">
                    <span x-show="!editing" x-text="labels[displayValue]" class="ds-badge" :class="classes[displayValue] + (justSaved ? ' mbr-saved' : '')">{{ $dueLabels[$due->displayStatus()] ?? $due->displayStatus() }}</span>
                    <div x-show="editing" @click.stop>
                        <select x-model="value" @change="save()" class="ds-select" aria-label="{{ __('members::admin.dues.status') }}">
                            <option value="ausstehend" x-text="labels.ausstehend"></option>
                            <option value="bezahlt" x-text="labels.bezahlt"></option>
                            <option value="ueberfaellig" x-text="labels.ueberfaellig"></option>
                        </select>
                        <p class="ds-field__error" x-show="error" x-text="error"></p>
                    </div>
                </td>
                @include('members::dues._paid-on-cell', ['due' => $due, 'cellClass' => 'mbr-due-paid'])
                <td class="mbr-due-toggle">
                    <x-ui.icon-button icon="chevron-down" variant="text" :label="__('members::admin.dues.details_for', ['year' => $due->fiscalYear->name])"
                        aria-controls="due-extra-{{ $due->id }}"
                        x-bind:aria-expanded="(!!openDues[{{ $due->id }}]).toString()"
                        x-bind:class="{ 'is-open': openDues[{{ $due->id }}] }"
                        x-on:click="openDues[{{ $due->id }}] = !openDues[{{ $due->id }}]" />
                </td>
            </tr>
            <tr class="mbr-due-extra" id="due-extra-{{ $due->id }}" x-show="openDues[{{ $due->id }}]" @unless($openDues[$due->id] ?? false) x-cloak @endunless>
                <td class="mbr-due-extra__spacer" aria-hidden="true"></td>
                <td class="cursor-pointer text-ink-2"
                    data-field="kommentar"
                    x-data="{
                        editing: false, error: null, justSaved: false,
                        value: @js($due->kommentar ?? ''),
                        display: @js($due->kommentar ?: '–'),
                        async save() {
                            this.error = null;
                            const result = await window.saveDueField({{ $due->id }}, 'kommentar', this.value || null);
                            if (result.ok) { this.editing = false; this.display = result.value; this.justSaved = true; setTimeout(() => this.justSaved = false, 1500); }
                            else { this.error = result.message; }
                        }
                    }"
                    @click="if (!editing) editing = true"
                    @click.outside="if (!error) editing = false">
                    <span class="mbr-due-extra__label">{{ __('members::admin.dues.kommentar') }}</span>
                    <span x-show="!editing" x-text="display" :class="{ 'mbr-saved': justSaved }">{{ $due->kommentar ?: '–' }}</span>
                    <div x-show="editing" @click.stop>
                        <input type="text" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" class="ds-input" :class="{ 'ds-input--invalid': error }" aria-label="{{ __('members::admin.dues.kommentar') }}">
                        <p class="ds-field__error" x-show="error" x-text="error"></p>
                    </div>
                </td>
                <td class="cursor-pointer tabular-nums" colspan="2"
                    data-field="teilbetrag"
                    x-data="{
                        editing: false, error: null, justSaved: false,
                        value: @js($due->teilbetrag ?? ''),
                        display: @js($teilbetragDisplay),
                        async save() {
                            this.error = null;
                            const result = await window.saveDueField({{ $due->id }}, 'teilbetrag', this.value || null);
                            if (result.ok) { this.editing = false; this.display = result.value; this.justSaved = true; setTimeout(() => this.justSaved = false, 1500); }
                            else { this.error = result.message; }
                        }
                    }"
                    @click="if (!editing) editing = true"
                    @click.outside="if (!error) editing = false">
                    <span class="mbr-due-extra__label">{{ __('members::admin.dues.teilbetrag') }}</span>
                    <span x-show="!editing" x-text="display" class="whitespace-nowrap" :class="{ 'mbr-partial': display !== '–', 'mbr-saved': justSaved }">{{ $teilbetragDisplay }}</span>
                    <div x-show="editing" @click.stop>
                        <input type="number" step="0.01" min="0" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" class="ds-input" :class="{ 'ds-input--invalid': error }" aria-label="{{ __('members::admin.dues.teilbetrag') }}">
                        <p class="ds-field__error" x-show="error" x-text="error"></p>
                    </div>
                </td>
                <td colspan="2" data-field="paid_through">
                    <label class="mbr-due-extra__label" for="due-paid-through-{{ $due->id }}">{{ __('members::admin.dues.paid_through') }}</label>
                    <select id="due-paid-through-{{ $due->id }}" class="ds-select" onchange="window.saveDuePaidThrough({{ $due->id }}, this.value)">
                        <option value="">–</option>
                        @foreach($fiscalYears->filter(fn ($fy) => $fy->starts_on->gte($due->fiscalYear->starts_on)) as $fiscalYear)
                            <option value="{{ $fiscalYear->id }}" @selected($due->bezahlt_bis_fiscal_year_id === $fiscalYear->id)>{{ $fiscalYear->name }}</option>
                        @endforeach
                    </select>
                </td>
            </tr>
            @endif
        @empty
            <tr><td colspan="6" class="text-ink-2">–</td></tr>
        @endforelse
    </x-ui.table>
    <div class="mbr-dues-card__footer">
        <x-ui.button variant="secondary" :block="false" :href="route('members.payments.create', $member)">{{ __('members::admin.payments.new_payment') }}</x-ui.button>
    </div>
</x-ui.card>
</div>

<div class="mbr-detail__side">
    {{-- Optional link to a booking-system account; members and users are separate things. --}}
    <x-ui.card :title="__('members::admin.members.user_account_card')" x-data="{
        linked: {{ $member->user !== null ? 'true' : 'false' }}, error: null,
        async unlink() {
            this.error = null;
            const result = await window.saveMemberField({{ $member->id }}, 'user_id', null);
            if (result.ok) { this.linked = false; this.$dispatch('member-unlinked'); } else { this.error = result.message; }
        }
    }">
        <div class="mbr-account-card">
        <p class="m-0 text-body-s text-ink-2">{{ __('members::admin.members.user_account_help') }}</p>
        @if($member->user)
            <div x-show="linked" class="flex flex-col items-start gap-3">
                <p class="font-semibold text-ink">{{ $member->user->alias }}@if($member->user->email) · {{ $member->user->email }}@endif</p>
                <x-ui.button variant="secondary" :block="false" x-on:click="unlink()">{{ __('members::admin.members.unlink') }}</x-ui.button>
                <p class="ds-field__error" x-show="error" x-text="error"></p>
            </div>
        @endif
        <div x-show="!linked"@if($member->user !== null) x-cloak @endif>
            @include('members::members._user-picker', ['member' => $member, 'formId' => 'member-edit-form', 'label' => __('members::admin.members.link_user'), 'help' => __('members::admin.members.link_hint')])
        </div>
        </div>
    </x-ui.card>

    @include('members::members._history', ['changes' => $changes, 'member' => $member])

    {{-- DSGVO: the anonymise action only appears once the retention period has really passed (the server re-checks). --}}
    @if($member->anonymized_at !== null)
        <x-ui.notice tone="neutral">{{ __('members::admin.members.anonymized_at', ['date' => $member->anonymized_at->format('d.m.Y')]) }}</x-ui.notice>
    @elseif($member->isAnonymizable())
        <x-ui.card class="mbr-privacy-card" :title="__('members::admin.members.privacy_card')">
            <div class="flex flex-col gap-3">
                <x-ui.notice tone="attention">{{ __('members::admin.members.anonymize_warning') }}</x-ui.notice>
                <form method="POST" action="{{ route('members.anonymize', $member) }}" onsubmit="const c = prompt({{ Js::from(__('members::admin.members.anonymize_confirmation_label')) }}); if (c === null) return false; this.querySelector('input[name=confirmation]').value = c;">
                    @csrf
                    <input type="hidden" name="confirmation" value="">
                    <x-ui.button type="submit" variant="danger">{{ __('members::admin.members.anonymize_button') }}</x-ui.button>
                </form>
            </div>
        </x-ui.card>
    @elseif($member->anonymizableFrom() !== null)
        <x-ui.card class="mbr-privacy-card" :title="__('members::admin.members.privacy_card')">
            <p class="m-0 text-body-s text-ink-2">{{ __('members::admin.members.anonymizable_from', ['date' => $member->anonymizableFrom()->format('d.m.Y'), 'years' => \App\Modules\Members\Models\Member::retentionYears()]) }}</p>
        </x-ui.card>
    @endif
</div>
</div>

@once
    @push('scripts')
        @include('members::partials.inline-save')
    @endpush
@endonce
@endsection
