@extends('members::layout')
@section('members-title', __('members::admin.members.title'))
@section('members-content')
@php
    use App\Modules\Members\Support\StatusTone;
    use App\Support\Ui\Initials;

    $statusFilter = $filters['status'] ?? 'aktiv';
    $statusLabels = [
        'aktiv' => __('members::admin.members.status_active'),
        'ruhend' => __('members::admin.members.status_ruhend'),
        'ausgetreten' => __('members::admin.members.status_departed'),
    ];
    $statusOptions = $statusLabels + ['all' => __('members::admin.common.all')];
    $dueLabels = [
        'bezahlt' => __('members::admin.dues.status_bezahlt'),
        'ausstehend' => __('members::admin.dues.status_ausstehend'),
        'ueberfaellig' => __('members::admin.dues.status_ueberfaellig'),
    ];
    $moreFiltersActive = isset($filters['fee_category']) || isset($filters['zahlung']);
@endphp
<x-members::page-header :title="__('members::admin.members.title')">
    <x-slot:actions>
        <x-ui.button variant="secondary" :block="false" :href="route('members.export')" class="mbr-phone-hidden">{{ __('members::admin.members.export') }}</x-ui.button>
        <x-ui.button :block="false" icon="plus" :href="route('members.create')" class="mbr-phone-hidden">{{ __('members::admin.members.create') }}</x-ui.button>
    </x-slot:actions>
    <x-slot:phoneAction>
        <x-ui.button :href="route('members.create')">{{ __('members::admin.members.create') }}</x-ui.button>
    </x-slot:phoneAction>
</x-members::page-header>

<x-ui.card class="mbr-filter-card" as="div">
    <form method="GET" action="{{ route('members.index') }}" class="mbr-filters">
        <x-ui.field class="mbr-filters__search" :label="__('members::admin.members.search')" for="mi-search">
            <x-ui.input name="search" :value="$filters['search'] ?? ''" />
        </x-ui.field>
        <div class="mbr-chips mbr-phone-only">
            @foreach($statusOptions as $status => $label)
                <x-ui.chip :href="route('members.index', array_merge($filters, ['status' => $status]))" :active="$statusFilter === $status">{{ $label }}</x-ui.chip>
            @endforeach
        </div>
        <x-ui.field class="mbr-filters__field mbr-phone-hidden" :label="__('members::admin.members.status')" for="mi-status">
            <x-ui.select name="status" :options="$statusOptions" :selected="$statusFilter" />
        </x-ui.field>
        <x-ui.disclosure class="mbr-filters__more" :summary="__('members::admin.common.filter')" :open="true"
            x-data="{ keepOpen: {{ $moreFiltersActive ? 'true' : 'false' }} }"
            x-init="const phone = window.matchMedia('(max-width: 47.99rem)'); const sync = () => { $el.open = keepOpen || !phone.matches; }; sync(); phone.addEventListener('change', sync)">
            <x-ui.field class="mbr-filters__field" :label="__('members::admin.members.fee_category')" for="mi-fee-category">
                <x-ui.select name="fee_category" :options="$feeCategories->pluck('name', 'id')->all()" :selected="$filters['fee_category'] ?? ''" :placeholder="__('members::admin.common.all')" />
            </x-ui.field>
            <x-ui.field class="mbr-filters__field" :label="__('members::admin.dues.title')" for="mi-zahlung">
                <x-ui.select name="zahlung" :options="$dueLabels" :selected="$filters['zahlung'] ?? ''" :placeholder="__('members::admin.common.all')" />
            </x-ui.field>
        </x-ui.disclosure>
        <x-ui.button type="submit" variant="secondary" class="mbr-filters__submit">{{ __('members::admin.common.filter') }}</x-ui.button>
    </form>
</x-ui.card>

<x-ui.card class="mbr-table-card" padding="none" as="div">
    <x-ui.table :caption="__('members::admin.members.title')">
        <x-slot:head>
            <tr>
                <th scope="col" class="mbr-phone-hidden">{{ __('members::admin.members.member_number') }}</th>
                <th scope="col">{{ __('members::admin.members.name') }}</th>
                <th scope="col" class="mbr-phone-hidden">{{ __('members::admin.members.age') }}</th>
                <th scope="col">{{ __('members::admin.members.status') }}</th>
                <th scope="col">{{ __('members::admin.dues.title') }}</th>
                <th scope="col">{{ __('members::admin.members.fee_category') }}</th>
                <th scope="col" class="mbr-phone-hidden">{{ __('members::admin.members.email') }}</th>
                <th scope="col" class="mbr-phone-hidden">{{ __('members::admin.members.phone') }}</th>
                <th scope="col" class="mbr-phone-hidden">{{ __('members::admin.members.booking_user_short') }}</th>
                <th scope="col" class="mbr-cell-actions">{{ __('members::admin.common.actions') }}</th>
            </tr>
        </x-slot:head>
        @foreach($members as $member)
            @php
                $latestDue = $member->dues->sortByDesc(fn ($due) => $due->fiscalYear->starts_on)->first();
            @endphp
            <tr>
                <td class="mbr-phone-hidden text-ink-2 cursor-pointer" data-label="{{ __('members::admin.members.member_number') }}" onclick="window.location.href='{{ route('members.edit', $member) }}'">
                    <a href="{{ route('members.edit', $member) }}" class="mbr-row-link">{{ $member->member_number }}</a>
                </td>
                <td class="ds-cell--title cursor-pointer"
                    data-field="firstname"
                    data-label="{{ __('members::admin.members.name') }}"
                    x-data="{
                        editing: false, error: null, justSaved: false,
                        firstname: @js($member->firstname),
                        lastname: @js($member->lastname),
                        async save(field) {
                            this.error = null;
                            const value = field === 'firstname' ? this.firstname : this.lastname;
                            const result = await window.saveMemberField({{ $member->id }}, field, value);
                            if (result.ok) {
                                this.editing = false;
                                this.justSaved = true;
                                setTimeout(() => this.justSaved = false, 1500);
                            } else {
                                this.error = result.message;
                            }
                        }
                    }"
                    @click="if (!editing) editing = true"
                    @click.outside="if (!error) editing = false">
                    <span x-show="!editing" class="mbr-name">
                        <span class="mbr-initials" aria-hidden="true">{{ Initials::from($member->fullName()) }}</span>
                        <span x-text="firstname + ' ' + lastname" :class="{ 'mbr-saved': justSaved }">{{ $member->fullName() }}</span>
                    </span>
                    <div x-show="editing" @click.stop class="flex flex-col gap-1">
                        <div class="flex gap-1">
                            <input type="text" x-model="firstname" @blur="save('firstname')" @keydown.enter.prevent="$event.target.blur()" class="ds-input" :class="{ 'ds-input--invalid': error }" aria-label="{{ __('members::admin.members.firstname') }}">
                            <input type="text" x-model="lastname" @blur="save('lastname')" @keydown.enter.prevent="$event.target.blur()" class="ds-input" :class="{ 'ds-input--invalid': error }" aria-label="{{ __('members::admin.members.lastname') }}">
                        </div>
                        <p class="ds-field__error" x-show="error" x-text="error"></p>
                    </div>
                </td>
                <td class="mbr-phone-hidden text-ink-2" data-label="{{ __('members::admin.members.age') }}">{{ $member->age() ?? '—' }}</td>
                <td class="cursor-pointer"
                    data-field="status"
                    data-label="{{ __('members::admin.members.status') }}"
                    x-data="{
                        editing: false, error: null, justSaved: false,
                        value: @js($member->status === 'ausgetreten' ? 'aktiv' : $member->status),
                        displayValue: @js($member->status),
                        labels: { aktiv: @js(__('members::admin.members.status_active')), ruhend: @js(__('members::admin.members.status_ruhend')), ausgetreten: @js(__('members::admin.members.status_departed')) },
                        classes: @js(StatusTone::badgeClasses(StatusTone::MEMBER)),
                        async save() {
                            this.error = null;
                            const result = await window.saveMemberField({{ $member->id }}, 'status', this.value);
                            if (result.ok) {
                                this.editing = false;
                                this.value = result.value;
                                this.displayValue = result.value;
                                this.justSaved = true;
                                setTimeout(() => this.justSaved = false, 1500);
                            } else {
                                this.error = result.message;
                            }
                        }
                    }"
                    @click="if (!editing) editing = true"
                    @click.outside="if (!error) editing = false">
                    <span x-show="!editing" x-text="labels[displayValue]" class="ds-badge" :class="classes[displayValue] + (justSaved ? ' mbr-saved' : '')">{{ $statusLabels[$member->status] ?? $member->status }}</span>
                    <div x-show="editing" @click.stop>
                        <select x-model="value" @change="save()" class="ds-select" aria-label="{{ __('members::admin.members.status') }}">
                            <option value="aktiv" x-text="labels.aktiv"></option>
                            <option value="ruhend" x-text="labels.ruhend"></option>
                        </select>
                        <p class="ds-field__error" x-show="error" x-text="error"></p>
                    </div>
                </td>
                <td class="{{ $latestDue ? 'cursor-pointer' : 'text-ink-2' }}"
                    data-field="due-status"
                    data-label="{{ __('members::admin.dues.title') }}"
                    @if($latestDue)
                        x-data="{
                            editing: false, error: null, justSaved: false,
                            value: @js($latestDue->status),
                            displayValue: @js($latestDue->displayStatus()),
                            bezahltBis: @js($latestDue->bezahlt_bis_fiscal_year_id ?? ''),
                            kommentar: @js($latestDue->kommentar ?? ''),
                            labels: { ausstehend: @js(__('members::admin.dues.status_ausstehend')), bezahlt: @js(__('members::admin.dues.status_bezahlt')), ueberfaellig: @js(__('members::admin.dues.status_ueberfaellig')) },
                            classes: @js(StatusTone::badgeClasses(StatusTone::DUE)),
                            async save() {
                                this.error = null;
                                const result = await window.saveDueField({{ $latestDue->id }}, 'status', this.value);
                                if (result.ok) {
                                    this.value = result.value;
                                    this.displayValue = result.value;
                                    this.justSaved = true;
                                    setTimeout(() => this.justSaved = false, 1500);
                                } else {
                                    this.error = result.message;
                                }
                            },
                            async saveKommentar() {
                                this.error = null;
                                const result = await window.saveDueField({{ $latestDue->id }}, 'kommentar', this.kommentar || null);
                                if (!result.ok) { this.error = result.message; }
                            },
                            async savePaidThrough() {
                                if (!this.bezahltBis) return;
                                await window.saveDuePaidThrough({{ $latestDue->id }}, this.bezahltBis);
                            }
                        }"
                        @click="if (!editing) editing = true"
                        @click.outside="if (!error) editing = false"
                    @endif
                    >
                    @if($latestDue)
                        <span x-show="!editing" x-text="labels[displayValue]" class="ds-badge" :class="classes[displayValue] + (justSaved ? ' mbr-saved' : '')">{{ $dueLabels[$latestDue->displayStatus()] ?? $latestDue->displayStatus() }}</span>
                        <div x-show="editing" @click.stop class="flex min-w-40 flex-col gap-1">
                            <select x-model="value" @change="save()" class="ds-select" aria-label="{{ __('members::admin.dues.title') }}">
                                <option value="ausstehend" x-text="labels.ausstehend"></option>
                                <option value="bezahlt" x-text="labels.bezahlt"></option>
                                <option value="ueberfaellig" x-text="labels.ueberfaellig"></option>
                            </select>
                            <select x-model="bezahltBis" @change="savePaidThrough()" class="ds-select" aria-label="{{ __('members::admin.dues.paid_through') }}">
                                <option value="">{{ __('members::admin.dues.paid_through') }}</option>
                                @foreach($fiscalYears as $fiscalYear)
                                    <option value="{{ $fiscalYear->id }}">{{ $fiscalYear->name }}</option>
                                @endforeach
                            </select>
                            <input type="text" x-model="kommentar" @blur="saveKommentar()" @keydown.enter.prevent="$event.target.blur()" placeholder="{{ __('members::admin.dues.kommentar') }}" aria-label="{{ __('members::admin.dues.kommentar') }}" class="ds-input" :class="{ 'ds-input--invalid': error }">
                            <p class="ds-field__error" x-show="error" x-text="error"></p>
                        </div>
                    @else
                        <span>–</span>
                    @endif
                </td>
                <td class="text-ink-2 cursor-pointer"
                    data-field="fee_category_id"
                    data-label="{{ __('members::admin.members.fee_category') }}"
                    x-data="{
                        editing: false, error: null, justSaved: false,
                        value: @js((string) ($member->fee_category_id ?? '')),
                        label: @js($member->feeCategory->name ?? '—'),
                        async save() {
                            this.error = null;
                            const result = await window.saveMemberField({{ $member->id }}, 'fee_category_id', this.value || null);
                            if (result.ok) {
                                this.editing = false;
                                this.label = result.value;
                                this.justSaved = true;
                                setTimeout(() => this.justSaved = false, 1500);
                            } else {
                                this.error = result.message;
                            }
                        }
                    }"
                    @click="if (!editing) editing = true"
                    @click.outside="if (!error) editing = false">
                    <span x-show="!editing" x-text="label" :class="{ 'mbr-saved': justSaved }">{{ $member->feeCategory->name ?? '—' }}</span>
                    <div x-show="editing" @click.stop>
                        <select x-model="value" @change="save()" class="ds-select" aria-label="{{ __('members::admin.members.fee_category') }}">
                            <option value=""></option>
                            @foreach($feeCategories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <p class="ds-field__error" x-show="error" x-text="error"></p>
                    </div>
                </td>
                <td class="mbr-phone-hidden text-ink-2 cursor-pointer"
                    data-field="email"
                    data-label="{{ __('members::admin.members.email') }}"
                    x-data="{
                        editing: false, error: null, justSaved: false,
                        value: @js($member->email ?? ''),
                        async save() {
                            this.error = null;
                            const result = await window.saveMemberField({{ $member->id }}, 'email', this.value || null);
                            if (result.ok) {
                                this.editing = false;
                                this.justSaved = true;
                                setTimeout(() => this.justSaved = false, 1500);
                            } else {
                                this.error = result.message;
                            }
                        }
                    }"
                    @click="if (!editing) editing = true"
                    @click.outside="if (!error) editing = false">
                    <span x-show="!editing" x-text="value || '—'" :class="{ 'mbr-saved': justSaved }">{{ $member->email ?: '—' }}</span>
                    <div x-show="editing" @click.stop>
                        <input type="email" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" class="ds-input" :class="{ 'ds-input--invalid': error }" aria-label="{{ __('members::admin.members.email') }}">
                        <p class="ds-field__error" x-show="error" x-text="error"></p>
                    </div>
                </td>
                <td class="mbr-phone-hidden text-ink-2 cursor-pointer"
                    data-field="phone"
                    data-label="{{ __('members::admin.members.phone') }}"
                    x-data="{
                        editing: false, error: null, justSaved: false,
                        value: @js($member->phone ?? ''),
                        async save() {
                            this.error = null;
                            const result = await window.saveMemberField({{ $member->id }}, 'phone', this.value || null);
                            if (result.ok) {
                                this.editing = false;
                                this.justSaved = true;
                                setTimeout(() => this.justSaved = false, 1500);
                            } else {
                                this.error = result.message;
                            }
                        }
                    }"
                    @click="if (!editing) editing = true"
                    @click.outside="if (!error) editing = false">
                    <span x-show="!editing" x-text="value || '—'" :class="{ 'mbr-saved': justSaved }">{{ $member->phone ?: '—' }}</span>
                    <div x-show="editing" @click.stop>
                        <input type="text" x-model="value" @blur="save()" @keydown.enter.prevent="$event.target.blur()" class="ds-input" :class="{ 'ds-input--invalid': error }" aria-label="{{ __('members::admin.members.phone') }}">
                        <p class="ds-field__error" x-show="error" x-text="error"></p>
                    </div>
                </td>
                <td class="mbr-phone-hidden" data-label="{{ __('members::admin.members.booking_user_short') }}">
                    @if($member->user_id !== null)
                        <x-ui.badge tone="good">{{ __('members::admin.common.yes') }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral">{{ __('members::admin.common.no') }}</x-ui.badge>
                    @endif
                </td>
                <td class="mbr-cell-actions" data-label="{{ __('members::admin.common.actions') }}">
                    <x-ui.button variant="secondary" :block="false" :href="route('members.edit', $member)">{{ __('members::admin.common.edit') }}</x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
    @if($members->hasPages())
        <div class="mbr-table-foot">
            <x-ui.pagination :paginator="$members" />
        </div>
    @endif
</x-ui.card>

@once
    @push('scripts')
        @include('members::partials.inline-save')
    @endpush
@endonce
@endsection
