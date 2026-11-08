@extends('members::layout')
@section('members-title', __('members::admin.dues.title'))
@section('members-content')
@php
    use App\Modules\Members\Support\MembersFormat;
    use App\Modules\Members\Support\StatusTone;

    $dueLabels = [
        'bezahlt' => __('members::admin.dues.status_bezahlt'),
        'ausstehend' => __('members::admin.dues.status_ausstehend'),
        'ueberfaellig' => __('members::admin.dues.status_ueberfaellig'),
    ];
    // "Offen" is the default (no status parameter); "Alle" is an explicit choice that stays in the URL.
    $statusFilterOptions = [
        'offen' => __('members::admin.dues.status_filter_offen'),
        'alle' => __('members::admin.dues.status_filter_alle'),
        'ausstehend' => __('members::admin.dues.status_filter_ausstehend'),
        'ueberfaellig' => __('members::admin.dues.status_filter_ueberfaellig'),
        'bezahlt' => __('members::admin.dues.status_filter_bezahlt'),
    ];
    $composeOpen = $errors->hasAny(['member_id', 'fee_category_id', 'amount', 'teilbetrag', 'kommentar']);
    $generateFiscalYear = $generateFiscalYears->firstWhere('id', $generateFiscalYearId);
    $selectedFY = $fiscalYears->firstWhere('id', $selectedFiscalYearId);
    $yearClosed = (bool) $selectedFiscalYear?->isClosed();
    $bulkPaidThroughYears = $fiscalYears->filter(fn ($fy) => $selectedFY === null || $fy->starts_on->gte($selectedFY->starts_on));
@endphp
<x-members::page-header :title="__('members::admin.dues.title')" />
@if($yearNotice)
    <x-ui.notice tone="attention">{{ $yearNotice }}</x-ui.notice>
@endif
@if($duesReport)
    @include('members::dues._generation-report', ['report' => $duesReport])
@endif

<x-ui.card class="mbr-dues-tools" as="div" x-data="{ composing: {{ $composeOpen ? 'true' : 'false' }} }">
    <form method="GET" action="{{ route('members.dues.index') }}" class="mbr-filters">
        <x-ui.field class="mbr-filters__field" :label="__('members::admin.dues.fiscal_year')" for="due-fiscal-year" :error="''">
            <x-ui.select name="fiscal_year_id" :options="$fiscalYears->mapWithKeys(fn ($fy) => [$fy->id => $fy->labelWithState()])->all()" :selected="(string) $selectedFiscalYearId" onchange="this.form.submit()" />
        </x-ui.field>
        <x-ui.field class="mbr-filters__search" :label="__('members::admin.members.search')" for="due-search">
            <x-ui.input name="search" :value="$filters['search'] ?? ''" />
        </x-ui.field>
        <x-ui.field class="mbr-filters__field" :label="__('members::admin.dues.status')" for="due-status">
            <x-ui.select name="status" :options="$statusFilterOptions" :selected="$filters['status']" />
        </x-ui.field>
        <x-ui.button type="submit" variant="secondary" :block="false" class="mbr-filters__submit">{{ __('members::admin.common.filter') }}</x-ui.button>
    </form>

    @if($yearClosed)
        <div class="flex flex-wrap items-center gap-2" data-testid="dues-year-closed">
            <x-ui.badge tone="neutral">{{ __('members::admin.fiscal_years.closed') }}</x-ui.badge>
            <span class="text-body-s text-ink-2">{{ __('members::admin.fiscal_years.closed_help') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('members.dues.generate') }}" class="mbr-filters mbr-dues-tools__row"
        x-data="{
            fiscalYearId: '{{ $generateFiscalYearId }}',
            names: @js($generateFiscalYears->mapWithKeys(fn ($fy) => [(string) $fy->id => $fy->name])),
            template: @js(__('members::admin.dues.generate_for', ['year' => '__YEAR__'])),
            get label() { return this.template.replace('__YEAR__', this.names[this.fiscalYearId] ?? ''); }
        }">
        @csrf
        <x-ui.field class="mbr-filters__field" :label="__('members::admin.dues.generate_year')" for="due-generate-year" :error="$errors->first('fiscal_year_id')">
            <x-ui.select name="fiscal_year_id" :options="$generateFiscalYears->pluck('name', 'id')->all()" :selected="(string) $generateFiscalYearId" x-model="fiscalYearId" />
        </x-ui.field>
        <x-ui.button type="submit" :block="false" x-text="label">{{ __('members::admin.dues.generate_for', ['year' => $generateFiscalYear->name ?? '']) }}</x-ui.button>
        @unless($yearClosed)
            <x-ui.button variant="secondary" :block="false" icon="plus" class="mbr-dues-tools__toggle" aria-controls="due-compose" x-bind:aria-expanded="composing.toString()" x-on:click="composing = !composing">{{ __('members::admin.dues.new') }}</x-ui.button>
        @endunless
    </form>

    @unless($yearClosed)
    <form id="due-compose" x-show="composing" @unless($composeOpen) x-cloak @endunless method="POST" action="{{ route('members.dues.store') }}" class="mbr-filters mbr-compose"
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
        <x-ui.field class="mbr-filters__search" :label="__('members::admin.dues.member')" for="due-new-member" :error="$errors->first('member_id')">
            <input type="text" id="due-new-member" list="due-inline-member-list" x-model="memberName" @input="resolveMember()" @change="resolveMember()" placeholder="{{ __('members::admin.members.search') }}" class="ds-input" autocomplete="off">
            <datalist id="due-inline-member-list">
                <template x-for="member in members" :key="member.id">
                    <option :value="member.name"></option>
                </template>
            </datalist>
            <input type="hidden" name="member_id" :value="memberId">
        </x-ui.field>
        <x-ui.field class="mbr-filters__field" :label="__('members::admin.members.fee_category')" for="due-new-category" :error="$errors->first('fee_category_id')">
            <x-ui.select name="fee_category_id" :options="$feeCategories->pluck('name', 'id')->all()" x-model="feeCategoryId" x-on:change="applyRate()" />
        </x-ui.field>
        <x-ui.field class="mbr-filters__field" :label="__('members::admin.fee_categories.amount')" for="due-new-amount" :error="$errors->first('amount')">
            <x-ui.input type="number" name="amount" step="0.01" min="0" x-model="amount" />
        </x-ui.field>
        <x-ui.field class="mbr-filters__field" :label="__('members::admin.dues.teilbetrag')" for="due-new-teilbetrag" :error="$errors->first('teilbetrag')">
            <x-ui.input type="number" name="teilbetrag" step="0.01" min="0" />
        </x-ui.field>
        <x-ui.field class="mbr-filters__search" :label="__('members::admin.dues.kommentar')" for="due-new-kommentar" :error="$errors->first('kommentar')">
            <x-ui.input name="kommentar" />
        </x-ui.field>
        <x-ui.button type="submit" :block="false">{{ __('members::admin.common.save') }}</x-ui.button>
    </form>
    @endunless
</x-ui.card>

@if(is_array($bulkPayReport))
    @php
        $skippedByReason = collect($bulkPayReport['skipped'] ?? [])->groupBy('reason');
    @endphp
    <x-ui.notice :tone="$skippedByReason->isEmpty() ? 'good' : 'attention'" :title="__('members::admin.dues.bulk_report_title')" data-testid="bulk-pay-report">
        <p class="m-0">{{ __('members::admin.dues.bulk_pay_result', ['paid' => $bulkPayReport['paid'] ?? 0, 'skipped' => count($bulkPayReport['skipped'] ?? [])]) }}</p>
        @if($skippedByReason->isNotEmpty())
            <ul class="m-0 mt-2 pl-5 list-disc">
                @foreach($skippedByReason as $reason => $rows)
                    <li><strong>{{ __('members::admin.dues.bulk_skip.'.$reason) }} ({{ $rows->count() }}):</strong> {{ $rows->pluck('label')->implode(', ') }}</li>
                @endforeach
            </ul>
        @endif
    </x-ui.notice>
@endif

{{--
    Bulk actions on the listed year. Without "Bezahlt bis" the form posts to members.dues.bulk-pay: one real payment
    per ticked due (or per open due of the whole filter, resolved again on the server), after a confirmation with
    count and sum. With "Bezahlt bis" it keeps the status-only multi-year action (members.dues.bulk-mark-paid).
--}}
<form method="POST" id="dues-bulk-form" action="{{ route('members.dues.bulk-pay') }}" class="flex flex-col gap-3"
    data-pay-url="{{ route('members.dues.bulk-pay') }}"
    data-mark-url="{{ route('members.dues.bulk-mark-paid') }}"
    data-payable-count="{{ $payableCount }}"
    data-payable-sum="{{ $payableSum }}"
    data-money="{{ str_replace(MembersFormat::amount(0), '__AMOUNT__', MembersFormat::money(0)) }}"
    x-bind:action="legacy() ? $el.dataset.markUrl : $el.dataset.payUrl"
    x-on:keydown.enter="if ($event.target.tagName === 'INPUT' && !confirming) { $event.preventDefault(); if (mode === 'filter' || selected.length > 0) openConfirm(); }"
    x-on:submit="if (submitting || !confirming) { $event.preventDefault(); return; } submitting = true"
    x-data="{
        selected: [],
        allIds: @js($dues->pluck('id')),
        mode: 'selected',
        paidThrough: '',
        paidAt: @js(old('paid_at', old('bezahlt_am', now()->toDateString()))),
        confirming: false,
        submitting: false,
        confirmCount: 0,
        confirmSum: '',
        payableCount: Number($el.dataset.payableCount),
        {{-- Household rows add 'h<id>' entries (their checkboxes post household_due_ids[] themselves). --}}
        households() { return this.selected.filter(id => String(id).startsWith('h')); },
        toggleHousehold(key, on) { this.selected = on ? [...this.selected, key] : this.selected.filter(id => id !== key); },
        get selectAll() { return this.allIds.length > 0 && this.selected.length - this.households().length === this.allIds.length; },
        set selectAll(value) { this.selected = [...(value ? this.allIds : []), ...this.households()]; if (!value) this.mode = 'selected'; },
        legacy() { return this.paidThrough !== '' && this.mode !== 'filter'; },
        money(value) {
            const lang = document.documentElement.lang || 'de';
            return this.$root.dataset.money.replace('__AMOUNT__', Number(value).toLocaleString(lang.startsWith('de') ? 'de-DE' : 'en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        },
        selectFilter() { this.mode = 'filter'; this.paidThrough = ''; },
        clearSelection() { this.mode = 'selected'; this.selected = []; this.$root.querySelectorAll('input[name=\'household_due_ids[]\']').forEach(box => box.checked = false); },
        openConfirm() {
            if (this.mode === 'filter') {
                this.confirmCount = this.payableCount;
                this.confirmSum = this.money(this.$root.dataset.payableSum);
            } else {
                const boxes = [...this.$root.querySelectorAll('input[data-amount]:checked')];
                this.confirmCount = boxes.length;
                this.confirmSum = this.money(boxes.reduce((total, box) => total + Number(box.dataset.amount), 0));
            }
            this.confirming = true;
        }
    }">
    @csrf
    <input type="hidden" name="fiscal_year_id" value="{{ $selectedFiscalYearId }}">
    <input type="hidden" name="mode" x-bind:value="mode" value="selected">
    <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
    <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
    <input type="hidden" name="bezahlt_am" x-bind:value="paidAt" value="{{ now()->toDateString() }}">
    @if($householdDues->isNotEmpty())
        @include('members::dues._household-dues')
    @endif
    <x-ui.card class="mbr-table-card" padding="none" as="div">
        @unless($yearClosed)
        <div class="mbr-filters mbr-bulk-bar">
            <span class="mbr-selected" x-show="mode === 'selected' && selected.length > 0" x-cloak x-text="@js(__('members::admin.dues.selected_count', ['count' => '__COUNT__'])).replace('__COUNT__', selected.length)"></span>
            <x-ui.field class="mbr-filters__field" :label="__('members::admin.dues.paid_through')" for="due-bulk-paid-through" :error="$errors->first('bezahlt_bis_fiscal_year_id') ?: $errors->first('due_ids')">
                <x-ui.select name="bezahlt_bis_fiscal_year_id" :options="$bulkPaidThroughYears->pluck('name', 'id')->all()" :placeholder="__('members::admin.common.no')" x-model="paidThrough" x-bind:disabled="mode === 'filter'" />
            </x-ui.field>
            <x-ui.field class="mbr-filters__field" :label="__('members::admin.dues.bezahlt_am')" for="due-bulk-paid-on" :error="$errors->first('paid_at') ?: $errors->first('bezahlt_am')">
                <x-ui.input type="date" name="paid_at" x-model="paidAt" :value="old('paid_at', old('bezahlt_am', now()->toDateString()))" :max="now()->toDateString()" :min="\App\Modules\Members\Models\MemberDue::PAID_ON_MIN" />
            </x-ui.field>
            <x-ui.field class="mbr-filters__search" :label="__('members::admin.dues.bulk_note')" for="due-bulk-note" :error="$errors->first('note')">
                <x-ui.input name="note" maxlength="255" :value="old('note')" x-bind:disabled="legacy()" />
            </x-ui.field>
            <x-ui.button id="dues-bulk-mark-paid" :block="false" x-bind:disabled="mode === 'selected' && selected.length === 0" x-on:click="openConfirm()">{{ __('members::admin.dues.bulk_pay') }}</x-ui.button>
        </div>
        @error('bulk')
            <p class="ds-field__error px-4 m-0" role="alert">{{ $message }}</p>
        @enderror
        @if($payableCount > 0)
            <p class="text-ink-2 px-4 py-2 m-0" x-show="mode === 'selected' && selectAll && payableCount > allIds.length" x-cloak>
                <button type="button" class="ds-btn ds-btn--text" x-on:click="selectFilter()">{{ __('members::admin.dues.bulk_select_filter', ['count' => $payableCount]) }}</button>
            </p>
            <p class="text-ink px-4 py-2 m-0" x-show="mode === 'filter'" x-cloak data-testid="bulk-filter-selected">
                <strong>{{ __('members::admin.dues.bulk_filter_selected', ['count' => $payableCount]) }}</strong>
                <button type="button" class="ds-btn ds-btn--text" x-on:click="clearSelection()">{{ __('members::admin.dues.bulk_clear_selection') }}</button>
            </p>
        @endif
        @endunless
        <p class="text-ink-2 px-4 py-2" data-testid="dues-outstanding-total">{{ __('members::admin.dues.outstanding_total') }}: <strong class="tabular-nums">{{ MembersFormat::money($outstandingTotal) }}</strong></p>
        <x-ui.table :caption="__('members::admin.dues.title')">
            <x-slot:head>
                <tr>
                    <th scope="col" class="mbr-check-cell"><label class="mbr-check"><input type="checkbox" x-model="selectAll" aria-label="{{ __('members::admin.common.select_all') }}"></label></th>
                    <th scope="col">{{ __('members::admin.members.name') }}</th>
                    <th scope="col" class="mbr-phone-hidden">{{ __('members::admin.dues.fiscal_year') }}</th>
                    <th scope="col" class="mbr-phone-hidden">{{ __('members::admin.members.fee_category') }}</th>
                    <th scope="col">{{ __('members::admin.dues.amount') }}</th>
                    <th scope="col">{{ __('members::admin.dues.outstanding') }}</th>
                    <th scope="col">{{ __('members::admin.dues.status') }}</th>
                    <th scope="col">{{ __('members::admin.dues.bezahlt_am') }}</th>
                    <th scope="col">{{ __('members::admin.dues.paid_through') }}</th>
                    <th scope="col">{{ __('members::admin.dues.teilbetrag') }}</th>
                    <th scope="col">{{ __('members::admin.dues.kommentar') }}</th>
                </tr>
            </x-slot:head>
            @forelse($dues as $due)
                @if($due->fiscalYear->isClosed())
                    @include('members::dues._locked-row', ['due' => $due, 'context' => 'list'])
                @else
                @php
                    $teilbetragDisplay = MembersFormat::optionalMoney($due->teilbetrag);
                @endphp
                <tr>
                    <td class="mbr-check-cell"><label class="mbr-check"><input type="checkbox" name="due_ids[]" value="{{ $due->id }}" @if($due->status !== 'bezahlt' && $due->payments->isEmpty()) data-amount="{{ number_format((float) $due->amount, 2, '.', '') }}" @endif x-model="selected" aria-label="{{ __('members::admin.common.select_row', ['name' => $due->member->fullName()]) }}"></label></td>
                    <td class="ds-cell--title" data-label="{{ __('members::admin.members.name') }}">{{ $due->member->fullName() }}</td>
                    <td class="mbr-phone-hidden text-ink-2" data-label="{{ __('members::admin.dues.fiscal_year') }}">{{ $due->fiscalYear->name }}</td>
                    <td class="mbr-phone-hidden text-ink-2" data-label="{{ __('members::admin.members.fee_category') }}">{{ $due->feeCategory->name }}</td>
                    <td class="tabular-nums whitespace-nowrap" data-label="{{ __('members::admin.dues.amount') }}">{{ MembersFormat::money($due->amount) }}</td>
                    <td class="tabular-nums whitespace-nowrap" data-label="{{ __('members::admin.dues.outstanding') }}">{{ $due->outstandingAmount() === '0.00' ? '–' : MembersFormat::money($due->outstandingAmount()) }}</td>
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
                                if (result.ok) {
                                    this.editing = false;
                                    this.value = result.value;
                                    // The server-computed overdue override (displayStatus()) only re-evaluates
                                    // on the next page load; right after a manual edit we show the raw value
                                    // the admin just chose, matching what was actually stored.
                                    this.displayValue = result.value;
                                    window.announceDuePaidOn({{ $due->id }}, result.data);
                                    this.justSaved = true;
                                    setTimeout(() => this.justSaved = false, 1500);
                                } else {
                                    this.error = result.message;
                                }
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
                        @click.outside="if (!error) editing = false">
                        <span x-show="!editing" x-text="display" :class="{ 'mbr-saved': justSaved }">{{ $due->kommentar ?: '–' }}</span>
                        <div x-show="editing" @click.stop>
                            <input type="text" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" class="ds-input" :class="{ 'ds-input--invalid': error }" aria-label="{{ __('members::admin.dues.kommentar') }}">
                            <p class="ds-field__error" x-show="error" x-text="error"></p>
                        </div>
                    </td>
                </tr>
                @endif
            @empty
                <tr><td colspan="11" class="text-ink-2">
                    @if(! $yearHasDues && $selectedFiscalYear)
                        <p class="m-0">{{ __('members::admin.dues.none_generated', ['year' => $selectedFiscalYear->name]) }}</p>
                        <p class="m-0 text-body-s">{{ __('members::admin.dues.none_generated_hint', ['year' => $selectedFiscalYear->name]) }}</p>
                    @else
                        {{ __('members::admin.dues.none_for_filters') }}
                    @endif
                </td></tr>
            @endforelse
        </x-ui.table>
        @if($dues->hasPages())
            <div class="mbr-table-foot">
                <x-ui.pagination :paginator="$dues" />
            </div>
        @endif
    </x-ui.card>
    @unless($yearClosed)
    <x-ui.modal id="dues-bulk-pay-confirm" show="confirming" close="confirming = false" :label="__('members::admin.dues.bulk_pay_confirm_title')">
        <x-slot:title>{{ __('members::admin.dues.bulk_pay_confirm_title') }}</x-slot:title>
        <p class="m-0 text-ink" x-text="(confirmCount === 1 ? @js(trans_choice('members::admin.dues.bulk_pay_confirm_text', 1, ['count' => '__COUNT__', 'sum' => '__SUM__'])) : @js(trans_choice('members::admin.dues.bulk_pay_confirm_text', 2, ['count' => '__COUNT__', 'sum' => '__SUM__']))).replace('__COUNT__', confirmCount).replace('__SUM__', confirmSum)"></p>
        <p class="m-0 mt-2 text-ink-2" x-show="legacy()">{{ __('members::admin.dues.bulk_pay_confirm_legacy') }}</p>
        <x-slot:footer>
            <x-ui.button variant="secondary" :block="false" x-on:click="confirming = false">{{ __('members::admin.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" :block="false" data-modal-initial x-bind:disabled="submitting">{{ __('members::admin.dues.bulk_pay_confirm') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
    @endunless
</form>

@once
    @push('scripts')
        @include('members::partials.inline-save')
    @endpush
@endonce
@endsection
