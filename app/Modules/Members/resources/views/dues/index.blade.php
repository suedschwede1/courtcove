@extends('members::layout')
@section('admin-title', __('members::admin.dues.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header"><h1>{{ __('members::admin.dues.title') }}</h1></div>

    @if(session('status'))
        <div class="ui-card"><div class="ui-card-body">{{ session('status') }}</div></div>
    @endif

    <div class="ui-card" x-data="{ composing: false }">
        <div class="ui-card-body ui-stack">
            <form method="GET" action="{{ route('members.dues.index') }}" class="ui-row">
                <div class="ui-field min-w-[12rem]">
                    <select name="fiscal_year_id" class="ui-select" onchange="this.form.submit()">
                        @foreach($fiscalYears as $fiscalYear)
                            <option value="{{ $fiscalYear->id }}" @selected($selectedFiscalYearId === $fiscalYear->id)>{{ $fiscalYear->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
            <form method="POST" action="{{ route('members.dues.generate') }}">
                @csrf
                <input type="hidden" name="fiscal_year_id" value="{{ $selectedFiscalYearId }}">
                <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.dues.generate') }}</button>
            </form>
            <button type="button" class="ui-btn ui-btn-ghost" @click="composing = !composing">{{ __('members::admin.dues.new') }}</button>
        </div>
        <form x-show="composing" method="POST" action="{{ route('members.dues.store') }}" class="ui-card-body ui-row" style="border-top:1px solid var(--ui-border)">
            @csrf
            <input type="hidden" name="fiscal_year_id" value="{{ $selectedFiscalYearId }}">
            <div class="ui-field min-w-[12rem]">
                <select id="due-inline-member" name="member_id" class="ui-select">
                    @foreach($members as $member)
                        <option value="{{ $member->id }}" data-fee-category="{{ $member->fee_category_id }}">{{ $member->fullName() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ui-field min-w-[10rem]">
                <select id="due-inline-category" name="fee_category_id" class="ui-select">
                    @foreach($feeCategories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ui-field min-w-[8rem]">
                <input type="number" step="0.01" min="0" name="amount" placeholder="{{ __('members::admin.fee_categories.amount') }}" class="ui-input">
            </div>
            <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.save') }}</button>
        </form>
    </div>

    <div class="ui-card">
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th>{{ __('members::admin.members.name') }}</th>
                        <th>{{ __('members::admin.fee_categories.title') }}</th>
                        <th>{{ __('members::admin.fee_categories.amount') }}</th>
                        <th>{{ __('members::admin.dues.status') }}</th>
                        <th>{{ __('members::admin.dues.due_date') }}</th>
                        <th>{{ __('members::admin.dues.paid_through') }}</th>
                        <th>{{ __('members::admin.dues.teilbetrag') }}</th>
                        <th>{{ __('members::admin.dues.kommentar') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dues as $due)
                        <tr>
                            <td>{{ $due->member->fullName() }}</td>
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
                                        if (result.ok) {
                                            this.editing = false;
                                            this.value = result.value;
                                            // The server-computed overdue override (displayStatus()) only re-evaluates
                                            // on the next page load; right after a manual edit we show the raw value
                                            // the admin just chose, matching what was actually stored.
                                            this.displayValue = result.value;
                                            this.justSaved = true;
                                            setTimeout(() => this.justSaved = false, 1500);
                                        } else {
                                            this.error = result.message;
                                        }
                                    }
                                }"
                                @click.outside="if (!error) editing = false">
                                <span x-show="!editing" @click="editing = true" x-text="labels[displayValue]" :class="'ui-badge ' + classes[displayValue]" class="cursor-pointer" :style="justSaved && 'outline: 2px solid #3e8635; outline-offset: 2px;'"></span>
                                <div x-show="editing">
                                    <select x-model="value" @change="save()" class="ui-select">
                                        <option value="ausstehend" x-text="labels.ausstehend"></option>
                                        <option value="bezahlt" x-text="labels.bezahlt"></option>
                                        <option value="ueberfaellig" x-text="labels.ueberfaellig"></option>
                                    </select>
                                    <p x-show="error" x-text="error" class="ui-error"></p>
                                </div>
                            </td>
                            <td data-field="faellig_am"
                                x-data="{
                                    editing: false, error: null, justSaved: false,
                                    value: @js($due->faellig_am?->format('Y-m-d') ?? ''),
                                    display: @js($due->faellig_am?->format('d.m.Y') ?? '–'),
                                    async save() {
                                        this.error = null;
                                        const result = await window.saveDueField({{ $due->id }}, 'faellig_am', this.value);
                                        if (result.ok) {
                                            this.editing = false;
                                            this.display = result.value;
                                            this.justSaved = true;
                                            setTimeout(() => this.justSaved = false, 1500);
                                        } else {
                                            this.error = result.message;
                                        }
                                    }
                                }"
                                @click.outside="if (!error) editing = false">
                                <span x-show="!editing" @click="editing = true" x-text="display" class="cursor-pointer" :style="justSaved && 'outline: 2px solid #3e8635; outline-offset: 2px;'"></span>
                                <div x-show="editing">
                                    <input type="date" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" :style="error && 'border-color:#c9190b'" class="ui-input">
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
                                        if (result.ok) {
                                            this.editing = false;
                                            this.display = result.value;
                                            this.justSaved = true;
                                            setTimeout(() => this.justSaved = false, 1500);
                                        } else {
                                            this.error = result.message;
                                        }
                                    }
                                }"
                                @click.outside="if (!error) editing = false">
                                <span x-show="!editing" @click="editing = true" x-text="display" class="cursor-pointer" :style="(display !== '–' ? 'color:#f0ab00;font-weight:500;' : '') + (justSaved ? 'outline: 2px solid #3e8635; outline-offset: 2px;' : '')"></span>
                                <div x-show="editing">
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
                                        if (result.ok) {
                                            this.editing = false;
                                            this.display = result.value;
                                            this.justSaved = true;
                                            setTimeout(() => this.justSaved = false, 1500);
                                        } else {
                                            this.error = result.message;
                                        }
                                    }
                                }"
                                @click.outside="if (!error) editing = false">
                                <span x-show="!editing" @click="editing = true" x-text="display" class="cursor-pointer" :style="justSaved && 'outline: 2px solid #3e8635; outline-offset: 2px;'"></span>
                                <div x-show="editing">
                                    <input type="text" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" :style="error && 'border-color:#c9190b'" class="ui-input">
                                    <p x-show="error" x-text="error" class="ui-error"></p>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    window.saveDueField = async (dueId, field, value) => {
        const token = document.querySelector('input[name="_token"]')?.value ?? '';

        try {
            const response = await fetch(`/members/dues/${dueId}/field`, {
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
            const response = await fetch(`/members/dues/${dueId}/paid-through`, {
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

    document.addEventListener('DOMContentLoaded', () => {
        const memberSelect = document.getElementById('due-inline-member');
        const categorySelect = document.getElementById('due-inline-category');
        if (!memberSelect || !categorySelect) return;
        memberSelect.addEventListener('change', () => {
            const category = memberSelect.selectedOptions[0]?.dataset.feeCategory;
            if (category) categorySelect.value = category;
        });
    });
</script>
@endsection
