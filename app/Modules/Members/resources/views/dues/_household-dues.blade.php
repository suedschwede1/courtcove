{{--
    Household dues (family fee) of the selected year, above the member dues and inside the bulk form:
    one row per household due (counted once), its covered members, the same inline cells as member dues
    (status, Bezahlt am, Bezahlt bis, Teilbetrag, Kommentar). Rows of a closed year are read-only.
    Needs $householdDues, $fiscalYears, $selectedFiscalYear, $dueLabels.
--}}
@php
    use App\Modules\Members\Support\MembersFormat;
    use App\Modules\Members\Support\StatusTone;

    $shortName = fn ($member) => trim($member->firstname.' '.mb_substr((string) $member->lastname, 0, 1).'.');
@endphp
<x-ui.card class="mbr-table-card" padding="none" as="section" :title="__('members::admin.dues.household_card', ['year' => $selectedFiscalYear?->name, 'count' => $householdDues->count()])" data-testid="dues-household-card">
    <x-ui.table :caption="__('members::admin.dues.household_card', ['year' => $selectedFiscalYear?->name, 'count' => $householdDues->count()])">
        <x-slot:head>
            <tr>
                <th scope="col" class="mbr-check-cell"><span class="sr-only">{{ __('members::admin.common.actions') }}</span></th>
                <th scope="col">{{ __('members::admin.households.col_household') }}</th>
                <th scope="col">{{ __('members::admin.households.col_members') }}</th>
                <th scope="col">{{ __('members::admin.dues.amount') }}</th>
                <th scope="col">{{ __('members::admin.dues.outstanding') }}</th>
                <th scope="col">{{ __('members::admin.dues.status') }}</th>
                <th scope="col">{{ __('members::admin.dues.bezahlt_am') }}</th>
                <th scope="col">{{ __('members::admin.dues.paid_through') }}</th>
                <th scope="col">{{ __('members::admin.dues.teilbetrag') }}</th>
                <th scope="col">{{ __('members::admin.dues.kommentar') }}</th>
            </tr>
        </x-slot:head>
        @foreach($householdDues as $hdue)
            @php
                $locked = $hdue->fiscalYear->isClosed();
                $names = $hdue->coveredMembers->map($shortName)->implode(', ') ?: '–';
                $outstanding = $hdue->outstandingAmount();
                $teilbetragDisplay = MembersFormat::optionalMoney($hdue->teilbetrag);
                $shownStatus = $hdue->payments->isNotEmpty() ? 'bezahlt' : $hdue->displayStatus();
            @endphp
            <tr data-household-due="{{ $hdue->id }}">
                <td class="mbr-check-cell">
                    @unless($locked)
                        <label class="mbr-check"><input type="checkbox" name="household_due_ids[]" value="{{ $hdue->id }}" x-on:change="toggleHousehold('h{{ $hdue->id }}', $event.target.checked)" aria-label="{{ __('members::admin.common.select_row', ['name' => $hdue->household->label()]) }}"></label>
                    @endunless
                </td>
                <td class="ds-cell--title" data-label="{{ __('members::admin.households.col_household') }}">
                    <a href="{{ route('members.households.show', $hdue->household_id) }}" class="mbr-row-link">{{ $hdue->household->label() }}</a>
                </td>
                <td class="text-ink-2" data-label="{{ __('members::admin.households.col_members') }}">{{ $names }}</td>
                <td class="tabular-nums whitespace-nowrap" data-label="{{ __('members::admin.dues.amount') }}">{{ MembersFormat::money($hdue->amount) }}</td>
                <td class="tabular-nums whitespace-nowrap" data-label="{{ __('members::admin.dues.outstanding') }}">{{ $outstanding === '0.00' ? '–' : MembersFormat::money($outstanding) }}</td>
                @if($locked)
                    <td data-label="{{ __('members::admin.dues.status') }}"><x-ui.badge :tone="StatusTone::due($shownStatus)">{{ $dueLabels[$shownStatus] ?? $shownStatus }}</x-ui.badge></td>
                    <td class="tabular-nums" data-label="{{ __('members::admin.dues.bezahlt_am') }}">{{ $hdue->paidOnDisplay() }}</td>
                    <td data-label="{{ __('members::admin.dues.paid_through') }}">{{ $fiscalYears->firstWhere('id', $hdue->bezahlt_bis_fiscal_year_id)->name ?? '–' }}</td>
                    <td class="tabular-nums" data-label="{{ __('members::admin.dues.teilbetrag') }}">{{ $teilbetragDisplay }}</td>
                    <td class="text-ink-2" data-label="{{ __('members::admin.dues.kommentar') }}">{{ $hdue->kommentar ?: '–' }}</td>
                @else
                    <td class="cursor-pointer"
                        data-field="status"
                        data-label="{{ __('members::admin.dues.status') }}"
                        x-data="{
                            editing: false, error: null, justSaved: false,
                            value: @js($hdue->status),
                            displayValue: @js($hdue->displayStatus()),
                            labels: { ausstehend: @js($dueLabels['ausstehend']), bezahlt: @js($dueLabels['bezahlt']), ueberfaellig: @js($dueLabels['ueberfaellig']) },
                            classes: @js(StatusTone::badgeClasses(StatusTone::DUE)),
                            async save() {
                                this.error = null;
                                const result = await window.saveHouseholdDueField({{ $hdue->id }}, 'status', this.value);
                                if (result.ok) { this.editing = false; this.value = result.value; this.displayValue = result.value; window.announceDuePaidOn({{ $hdue->id }}, result.data, 'household'); this.justSaved = true; setTimeout(() => this.justSaved = false, 1500); }
                                else { this.error = result.message; }
                            }
                        }"
                        @click="if (!editing) editing = true"
                        @click.outside="if (!error) editing = false">
                        <span x-show="!editing" x-text="labels[displayValue]" class="ds-badge" :class="classes[displayValue] + (justSaved ? ' mbr-saved' : '')">{{ $dueLabels[$hdue->displayStatus()] ?? $hdue->displayStatus() }}</span>
                        <div x-show="editing" @click.stop>
                            <select x-model="value" @change="save()" class="ds-select" aria-label="{{ __('members::admin.dues.status') }}">
                                <option value="ausstehend" x-text="labels.ausstehend"></option>
                                <option value="bezahlt" x-text="labels.bezahlt"></option>
                                <option value="ueberfaellig" x-text="labels.ueberfaellig"></option>
                            </select>
                            <p class="ds-field__error" x-show="error" x-text="error"></p>
                        </div>
                    </td>
                    @include('members::dues._paid-on-cell', ['due' => $hdue, 'kind' => 'household'])
                    <td data-label="{{ __('members::admin.dues.paid_through') }}">
                        <select class="ds-select" aria-label="{{ __('members::admin.dues.paid_through') }}" onchange="window.saveHouseholdDuePaidThrough({{ $hdue->id }}, this.value)">
                            <option value="">–</option>
                            @foreach($fiscalYears->filter(fn ($fy) => $fy->starts_on->gte($hdue->fiscalYear->starts_on)) as $fiscalYear)
                                <option value="{{ $fiscalYear->id }}" @selected($hdue->bezahlt_bis_fiscal_year_id === $fiscalYear->id)>{{ $fiscalYear->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="cursor-pointer tabular-nums"
                        data-field="teilbetrag"
                        data-label="{{ __('members::admin.dues.teilbetrag') }}"
                        x-data="{
                            editing: false, error: null, justSaved: false,
                            value: @js($hdue->teilbetrag ?? ''),
                            display: @js($teilbetragDisplay),
                            async save() {
                                this.error = null;
                                const result = await window.saveHouseholdDueField({{ $hdue->id }}, 'teilbetrag', this.value || null);
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
                            value: @js($hdue->kommentar ?? ''),
                            display: @js($hdue->kommentar ?: '–'),
                            async save() {
                                this.error = null;
                                const result = await window.saveHouseholdDueField({{ $hdue->id }}, 'kommentar', this.value || null);
                                if (result.ok) { this.editing = false; this.display = result.value; this.justSaved = true; setTimeout(() => this.justSaved = false, 1500); }
                                else { this.error = result.message; }
                            }
                        }"
                        @click="if (!editing) editing = true"
                        @click.outside="if (!error) editing = false">
                        <span x-show="!editing" x-text="display" :class="{ 'mbr-saved': justSaved }">{{ $hdue->kommentar ?: '–' }}</span>
                        <div x-show="editing" @click.stop>
                            <input type="text" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" class="ds-input" :class="{ 'ds-input--invalid': error }" aria-label="{{ __('members::admin.dues.kommentar') }}">
                            <p class="ds-field__error" x-show="error" x-text="error"></p>
                        </div>
                    </td>
                @endif
            </tr>
        @endforeach
    </x-ui.table>
</x-ui.card>
