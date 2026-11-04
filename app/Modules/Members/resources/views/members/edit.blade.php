@extends('members::layout')
@section('admin-title', __('members::admin.members.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header flex flex-wrap items-center justify-between gap-4">
        <h1>{{ $member->fullName() }}</h1>
        <div class="flex items-center gap-2">
            @if($member->left_at !== null && $member->anonymized_at === null)
                <form method="POST" action="{{ route('members.anonymize', $member) }}" onsubmit="const c = prompt({{ Js::from(__('members::admin.members.anonymize_confirmation_label')) }}); if (c === null) return false; this.querySelector('input[name=confirmation]').value = c;">
                    @csrf
                    <input type="hidden" name="confirmation" value="">
                    <button type="submit" class="ui-btn ui-btn-danger">{{ __('members::admin.members.delete') }}</button>
                </form>
            @endif
            <a href="{{ route('members.index') }}" class="ui-btn ui-btn-outline">{{ __('members::admin.common.cancel') }}</a>
            <button type="submit" form="member-edit-form" class="ui-btn ui-btn-primary">{{ __('members::admin.common.save') }}</button>
        </div>
    </div>

    <form id="member-edit-form" method="POST" action="{{ route('members.update', $member) }}" class="ui-form-shell">
        @method('PUT')
        @include('members::members._form', ['member' => $member])
    </form>

    <div class="ui-card" x-data="{ composing: false }">
        <div class="ui-card-header flex items-center justify-between">
            <h2>{{ __('members::admin.dues.title') }}</h2>
            <button type="button" class="ui-btn ui-btn-ghost" @click="composing = !composing">{{ __('members::admin.dues.new') }}</button>
        </div>
        <form x-show="composing" method="POST" action="{{ route('members.dues.store') }}" class="ui-card-body ui-row" style="border-bottom:1px solid var(--ui-border)">
            @csrf
            <input type="hidden" name="member_id" value="{{ $member->id }}">
            <input type="hidden" name="fee_category_id" value="{{ $member->fee_category_id }}">
            <div class="ui-field min-w-[10rem]">
                <label class="ui-label">{{ __('members::admin.members.fee_category') }}</label>
                <div class="ui-readonly-box">{{ $member->feeCategory->name ?? '—' }}</div>
            </div>
            <div class="ui-field min-w-[10rem]">
                <select name="fiscal_year_id" class="ui-select">
                    @foreach($fiscalYears as $fiscalYear)
                        <option value="{{ $fiscalYear->id }}">{{ $fiscalYear->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ui-field min-w-[8rem]">
                <input type="number" step="0.01" min="0" name="teilbetrag" placeholder="{{ __('members::admin.dues.teilbetrag') }}" class="ui-input">
            </div>
            <div class="ui-field min-w-[12rem]">
                <input type="text" name="kommentar" placeholder="{{ __('members::admin.dues.kommentar') }}" class="ui-input">
            </div>
            <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.save') }}</button>
        </form>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th>{{ __('members::admin.fiscal_years.title') }}</th>
                        <th>{{ __('members::admin.fee_categories.title') }}</th>
                        <th>{{ __('members::admin.fee_categories.amount') }}</th>
                        <th>{{ __('members::admin.dues.status') }}</th>
                        <th>{{ __('members::admin.dues.paid_through') }}</th>
                        <th>{{ __('members::admin.dues.teilbetrag') }}</th>
                        <th>{{ __('members::admin.dues.kommentar') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dues as $due)
                        <tr>
                            <td>{{ $due->fiscalYear->name }}</td>
                            <td>{{ $due->feeCategory->name }}</td>
                            <td>{{ number_format((float) $due->amount, 2) }} €</td>
                            <td data-field="status"
                                x-data="{
                                    editing: false, error: null, justSaved: false,
                                    value: @js($due->status),
                                    displayValue: @js($due->displayStatus()),
                                    labels: { ausstehend: @js(__('members::admin.dues.status_ausstehend')), bezahlt: @js(__('members::admin.dues.status_bezahlt')), ueberfaellig: @js(__('members::admin.dues.status_ueberfaellig')) },
                                    classes: { ausstehend: 'ui-badge-warning', bezahlt: 'ui-badge-success', ueberfaellig: 'ui-badge-danger' },
                                    async save() {
                                        this.error = null;
                                        const result = await window.saveDueField({{ $due->id }}, 'status', this.value);
                                        if (result.ok) { this.editing = false; this.value = result.value; this.displayValue = result.value; this.justSaved = true; setTimeout(() => this.justSaved = false, 1500); }
                                        else { this.error = result.message; }
                                    }
                                }"
                                @click="if (!editing) editing = true"
                                @click.outside="if (!error) editing = false"
                                class="cursor-pointer">
                                <span x-show="!editing" x-text="labels[displayValue]" :class="'ui-badge ' + classes[displayValue]" :style="justSaved && 'outline: 2px solid #3e8635; outline-offset: 2px;'"></span>
                                <div x-show="editing" @click.stop>
                                    <select x-model="value" @change="save()" class="ui-select">
                                        <option value="ausstehend" x-text="labels.ausstehend"></option>
                                        <option value="bezahlt" x-text="labels.bezahlt"></option>
                                        <option value="ueberfaellig" x-text="labels.ueberfaellig"></option>
                                    </select>
                                    <p x-show="error" x-text="error" class="ui-error"></p>
                                </div>
                            </td>
                            <td>
                                <select class="ui-select" onchange="window.saveDuePaidThrough({{ $due->id }}, this.value)">
                                    <option value="">–</option>
                                    @foreach($fiscalYears->filter(fn ($fy) => $fy->starts_on->gte($due->fiscalYear->starts_on)) as $fiscalYear)
                                        <option value="{{ $fiscalYear->id }}" @selected($due->bezahlt_bis_fiscal_year_id === $fiscalYear->id)>{{ $fiscalYear->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td data-field="teilbetrag"
                                x-data="{
                                    editing: false, error: null, justSaved: false,
                                    value: @js($due->teilbetrag ?? ''),
                                    display: @js($due->teilbetrag !== null ? number_format((float) $due->teilbetrag, 2, ',', '.').' €' : '–'),
                                    async save() {
                                        this.error = null;
                                        const result = await window.saveDueField({{ $due->id }}, 'teilbetrag', this.value || null);
                                        if (result.ok) { this.editing = false; this.display = result.value; this.justSaved = true; setTimeout(() => this.justSaved = false, 1500); }
                                        else { this.error = result.message; }
                                    }
                                }"
                                @click="if (!editing) editing = true"
                                @click.outside="if (!error) editing = false"
                                class="cursor-pointer">
                                <span x-show="!editing" x-text="display" :style="(display !== '–' ? 'color:#f0ab00;font-weight:500;' : '') + (justSaved ? 'outline: 2px solid #3e8635; outline-offset: 2px;' : '')"></span>
                                <div x-show="editing" @click.stop>
                                    <input type="number" step="0.01" min="0" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" :style="error && 'border-color:#c9190b'" class="ui-input">
                                    <p x-show="error" x-text="error" class="ui-error"></p>
                                </div>
                            </td>
                            <td data-field="kommentar"
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
                                @click.outside="if (!error) editing = false"
                                class="cursor-pointer">
                                <span x-show="!editing" x-text="display" :style="justSaved && 'outline: 2px solid #3e8635; outline-offset: 2px;'"></span>
                                <div x-show="editing" @click.stop>
                                    <input type="text" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" :style="error && 'border-color:#c9190b'" class="ui-input">
                                    <p x-show="error" x-text="error" class="ui-error"></p>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-[#6a6e73]">–</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($member->anonymized_at !== null)
        <div class="ui-card">
            <div class="ui-card-body">
                <p>{{ __('members::admin.members.anonymized_at', ['date' => $member->anonymized_at->format('d.m.Y')]) }}</p>
            </div>
        </div>
    @elseif($member->left_at !== null)
        <div class="ui-card">
            <div class="ui-card-body">
                <p class="text-[#6a6e73]">{{ __('members::admin.members.anonymize_warning') }}</p>
            </div>
        </div>
    @endif

    @include('members::members._history', ['changes' => $changes])
</div>

<script>
    window.saveMemberField = window.saveMemberField || (async (memberId, field, value) => {
        const token = document.querySelector('input[name="_token"]')?.value ?? '';

        try {
            const response = await fetch(`{{ url('/members') }}/${memberId}/field`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ field, value }),
            });
            const data = await response.json();

            if (!response.ok) {
                return { ok: false, message: data.message || 'Fehler beim Speichern.' };
            }

            return { ok: true, value: data.value };
        } catch (e) {
            return { ok: false, message: 'Fehler beim Speichern.' };
        }
    });

    window.saveDueField = async (dueId, field, value) => {
        const token = document.querySelector('input[name="_token"]')?.value ?? '';

        try {
            const response = await fetch(`{{ url('/members') }}/dues/${dueId}/field`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ field, value }),
            });
            const data = await response.json();

            if (!response.ok) {
                return { ok: false, message: data.message || 'Fehler beim Speichern.' };
            }

            return { ok: true, value: data.value };
        } catch (e) {
            return { ok: false, message: 'Fehler beim Speichern.' };
        }
    };

    window.saveDuePaidThrough = async (dueId, fiscalYearId) => {
        if (!fiscalYearId) return;
        const token = document.querySelector('input[name="_token"]')?.value ?? '';

        try {
            const response = await fetch(`{{ url('/members') }}/dues/${dueId}/paid-through`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ fiscal_year_id: fiscalYearId }),
            });

            if (response.ok) {
                window.location.reload();
                return;
            }

            const data = await response.json();
            alert(data.message || 'Fehler beim Speichern.');
            window.location.reload();
        } catch (e) {
            alert('Fehler beim Speichern.');
            window.location.reload();
        }
    };
</script>
@endsection
