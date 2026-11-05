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
                <div class="ui-field min-w-[10rem]">
                    <label class="ui-label" for="due-fiscal-year">{{ __('members::admin.fiscal_years.title') }}</label>
                    <select id="due-fiscal-year" name="fiscal_year_id" class="ui-select" onchange="this.form.submit()">
                        @foreach($fiscalYears as $fiscalYear)
                            <option value="{{ $fiscalYear->id }}" @selected($selectedFiscalYearId === $fiscalYear->id)>{{ $fiscalYear->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ui-field min-w-[14rem]">
                    <label class="ui-label" for="due-search">{{ __('members::admin.members.search') }}</label>
                    <input id="due-search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="ui-input">
                </div>
                <div class="ui-field min-w-[12rem]">
                    <label class="ui-label" for="due-status">{{ __('members::admin.dues.status') }}</label>
                    <select id="due-status" name="status" class="ui-select">
                        <option value="">{{ __('members::admin.common.all') }}</option>
                        <option value="bezahlt" @selected(($filters['status'] ?? '') === 'bezahlt')>{{ __('members::admin.dues.status_bezahlt') }}</option>
                        <option value="ausstehend" @selected(($filters['status'] ?? '') === 'ausstehend')>{{ __('members::admin.dues.status_ausstehend') }}</option>
                        <option value="ueberfaellig" @selected(($filters['status'] ?? '') === 'ueberfaellig')>{{ __('members::admin.dues.status_ueberfaellig') }}</option>
                    </select>
                </div>
                <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.filter') }}</button>
            </form>
            <form method="POST" action="{{ route('members.dues.generate') }}" class="ui-row"
                x-data="{
                    fiscalYearId: '{{ $defaultFiscalYearId }}',
                    names: @js($generateFiscalYears->mapWithKeys(fn ($fy) => [(string) $fy->id => $fy->name])),
                    template: @js(__('members::admin.dues.generate_for', ['year' => '__YEAR__'])),
                    get label() { return this.template.replace('__YEAR__', this.names[this.fiscalYearId] ?? ''); }
                }">
                @csrf
                <div class="ui-field min-w-[10rem]">
                    <select name="fiscal_year_id" x-model="fiscalYearId" class="ui-select">
                        @foreach($generateFiscalYears as $fiscalYear)
                            <option value="{{ $fiscalYear->id }}">{{ $fiscalYear->name }}</option>
                        @endforeach
                    </select>
                </div>
                @php $defaultFiscalYear = $generateFiscalYears->firstWhere('id', $defaultFiscalYearId); @endphp
                <button type="submit" class="ui-btn ui-btn-primary" x-text="label">{{ __('members::admin.dues.generate_for', ['year' => $defaultFiscalYear->name ?? '']) }}</button>
            </form>
            <button type="button" class="ui-btn ui-btn-ghost" @click="composing = !composing">{{ __('members::admin.dues.new') }}</button>
        </div>
        <form x-show="composing" method="POST" action="{{ route('members.dues.store') }}" class="ui-card-body ui-row" style="border-top:1px solid var(--ui-border)"
            x-data="{
                memberName: '',
                memberId: '',
                feeCategoryId: '',
                amount: '',
                members: @js($members->map(fn ($member) => ['id' => $member->id, 'name' => $member->fullName(), 'feeCategoryId' => $member->fee_category_id])->values()),
                rates: @js($feeCategoryRates ?? []),
                resolveMember() {
                    const match = this.members.find(m => m.name === this.memberName);
                    this.memberId = match ? match.id : '';
                    if (match && match.feeCategoryId) { this.feeCategoryId = String(match.feeCategoryId); }
                    this.applyRate();
                },
                applyRate() {
                    const rate = this.rates[this.feeCategoryId];
                    if (rate !== undefined) { this.amount = rate; }
                }
            }">
            @csrf
            <input type="hidden" name="fiscal_year_id" value="{{ $selectedFiscalYearId }}">
            <div class="ui-field min-w-[12rem]">
                <input type="text" list="due-inline-member-list" x-model="memberName" @input="resolveMember()" @change="resolveMember()" placeholder="{{ __('members::admin.members.search') }}" class="ui-input" autocomplete="off">
                <datalist id="due-inline-member-list">
                    <template x-for="member in members" :key="member.id">
                        <option :value="member.name"></option>
                    </template>
                </datalist>
                <input type="hidden" name="member_id" :value="memberId">
            </div>
            <div class="ui-field min-w-[10rem]">
                <select name="fee_category_id" class="ui-select" x-model="feeCategoryId" @change="applyRate()">
                    @foreach($feeCategories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ui-field min-w-[8rem]">
                <input type="number" step="0.01" min="0" name="amount" x-model="amount" placeholder="{{ __('members::admin.fee_categories.amount') }}" class="ui-input">
            </div>
            <div class="ui-field min-w-[8rem]">
                <input type="number" step="0.01" min="0" name="teilbetrag" placeholder="{{ __('members::admin.dues.teilbetrag') }}" class="ui-input">
            </div>
            <div class="ui-field min-w-[12rem]">
                <input type="text" name="kommentar" placeholder="{{ __('members::admin.dues.kommentar') }}" class="ui-input">
            </div>
            <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.save') }}</button>
        </form>
    </div>

    <form method="POST" action="{{ route('members.dues.bulk-mark-paid') }}"
        x-data="{
            selected: [],
            allIds: @js($dues->pluck('id')),
            get selectAll() { return this.allIds.length > 0 && this.selected.length === this.allIds.length; },
            set selectAll(value) { this.selected = value ? [...this.allIds] : []; }
        }">
        @csrf
        <input type="hidden" name="fiscal_year_id" value="{{ $selectedFiscalYearId }}">
        <div class="ui-card">
            @php
                $selectedFY = $fiscalYears->firstWhere('id', $selectedFiscalYearId);
                $bulkPaidThroughYears = $fiscalYears->filter(fn ($fy) => $selectedFY === null || $fy->starts_on->gte($selectedFY->starts_on));
            @endphp
            <div class="ui-card-body flex items-center justify-between gap-4" style="border-bottom:1px solid var(--ui-border)">
                <span class="ui-kpi-meta" x-show="selected.length > 0" x-text="'{{ __('members::admin.dues.selected_count', ['count' => '__COUNT__']) }}'.replace('__COUNT__', selected.length)"></span>
                <div class="flex items-center gap-2">
                    <select name="bezahlt_bis_fiscal_year_id" class="ui-select">
                        <option value="">{{ __('members::admin.dues.paid_through') }} ({{ __('members::admin.common.no') }})</option>
                        @foreach($bulkPaidThroughYears as $fiscalYear)
                            <option value="{{ $fiscalYear->id }}">{{ __('members::admin.dues.paid_through') }}: {{ $fiscalYear->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" id="dues-bulk-mark-paid" class="ui-btn ui-btn-primary" :disabled="selected.length === 0" :style="selected.length === 0 && 'opacity:0.5;cursor:not-allowed;'">{{ __('members::admin.dues.bulk_mark_paid') }}</button>
                </div>
            </div>
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" x-model="selectAll"></th>
                            <th>{{ __('members::admin.members.name') }}</th>
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
                        @foreach($dues as $due)
                            <tr>
                                <td><input type="checkbox" name="due_ids[]" value="{{ $due->id }}" x-model="selected"></td>
                                <td>{{ $due->member->fullName() }}</td>
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
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($dues->hasPages())
            <div class="ui-card-body">
                {{ $dues->links() }}
            </div>
        @endif

    </div>
    </form>
</div>

@once
    @push('scripts')
        @include('members::partials.inline-save')
    @endpush
@endonce
@endsection
