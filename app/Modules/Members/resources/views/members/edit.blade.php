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

<x-ui.card class="mbr-dues-card" padding="none" :title="__('members::admin.dues.title')" x-data="{ composing: {{ $composeOpen ? 'true' : 'false' }} }">
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
            <x-ui.select name="fiscal_year_id" :options="$fiscalYears->pluck('name', 'id')->all()" />
        </x-ui.field>
        <x-ui.field class="mbr-filters__field" :label="__('members::admin.dues.teilbetrag')" for="md-teilbetrag" :error="$errors->first('teilbetrag')">
            <x-ui.input type="number" name="teilbetrag" step="0.01" min="0" />
        </x-ui.field>
        <x-ui.field class="mbr-filters__search" :label="__('members::admin.dues.kommentar')" for="md-kommentar" :error="$errors->first('kommentar')">
            <x-ui.input name="kommentar" />
        </x-ui.field>
        <x-ui.button type="submit" :block="false">{{ __('members::admin.common.save') }}</x-ui.button>
    </form>
    <x-ui.table :caption="__('members::admin.dues.title')">
        <x-slot:head>
            <tr>
                <th scope="col">{{ __('members::admin.dues.fiscal_year') }}</th>
                <th scope="col">{{ __('members::admin.members.fee_category') }}</th>
                <th scope="col">{{ __('members::admin.dues.amount') }}</th>
                <th scope="col">{{ __('members::admin.dues.status') }}</th>
                <th scope="col">{{ __('members::admin.dues.bezahlt_am') }}</th>
                <th scope="col">{{ __('members::admin.dues.paid_through') }}</th>
                <th scope="col">{{ __('members::admin.dues.teilbetrag') }}</th>
                <th scope="col">{{ __('members::admin.dues.kommentar') }}</th>
            </tr>
        </x-slot:head>
        @forelse($dues as $due)
            @php
                $teilbetragDisplay = MembersFormat::optionalMoney($due->teilbetrag);
            @endphp
            <tr>
                <td class="ds-cell--title" data-label="{{ __('members::admin.dues.fiscal_year') }}">{{ $due->fiscalYear->name }}</td>
                <td class="text-ink-2" data-label="{{ __('members::admin.members.fee_category') }}">{{ $due->feeCategory->name }}</td>
                <td class="tabular-nums" data-label="{{ __('members::admin.dues.amount') }}">{{ MembersFormat::money($due->amount) }}</td>
                <td class="cursor-pointer"
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
                @include('members::dues._paid-on-cell', ['due' => $due])
                <td data-label="{{ __('members::admin.dues.paid_through') }}">
                    <select class="ds-select" aria-label="{{ __('members::admin.dues.paid_through') }}" onchange="window.saveDuePaidThrough({{ $due->id }}, this.value)">
                        <option value="">–</option>
                        @foreach($fiscalYears->filter(fn ($fy) => $fy->starts_on->gte($due->fiscalYear->starts_on)) as $fiscalYear)
                            <option value="{{ $fiscalYear->id }}" @selected($due->bezahlt_bis_fiscal_year_id === $fiscalYear->id)>{{ $fiscalYear->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td class="cursor-pointer tabular-nums"
                    data-field="teilbetrag"
                    data-label="{{ __('members::admin.dues.teilbetrag') }}"
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
                    <span x-show="!editing" x-text="display" :class="{ 'mbr-partial': display !== '–', 'mbr-saved': justSaved }">{{ $teilbetragDisplay }}</span>
                    <div x-show="editing" @click.stop>
                        <input type="number" step="0.01" min="0" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" class="ds-input" :class="{ 'ds-input--invalid': error }" aria-label="{{ __('members::admin.dues.teilbetrag') }}">
                        <p class="ds-field__error" x-show="error" x-text="error"></p>
                    </div>
                </td>
                <td class="cursor-pointer text-ink-2"
                    data-field="kommentar"
                    data-label="{{ __('members::admin.dues.kommentar') }}"
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
                    <span x-show="!editing" x-text="display" :class="{ 'mbr-saved': justSaved }">{{ $due->kommentar ?: '–' }}</span>
                    <div x-show="editing" @click.stop>
                        <input type="text" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" class="ds-input" :class="{ 'ds-input--invalid': error }" aria-label="{{ __('members::admin.dues.kommentar') }}">
                        <p class="ds-field__error" x-show="error" x-text="error"></p>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-ink-2">–</td></tr>
        @endforelse
    </x-ui.table>
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
