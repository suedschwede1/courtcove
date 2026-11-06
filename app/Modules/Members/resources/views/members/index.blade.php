@extends('members::layout')
@section('members-title', __('members::admin.members.title'))
@section('members-content')
@php
    use App\Modules\Members\Models\Member;
    use App\Modules\Members\Support\MemberListFilter;
    use App\Modules\Members\Support\StatusTone;
    use Illuminate\Support\Facades\Gate;

    $chipLabels = [
        'alle' => __('members::admin.members.chip_alle'),
        'aktiv' => __('members::admin.members.chip_aktiv'),
        'ruhend' => __('members::admin.members.chip_ruhend'),
        'jugend' => __('members::admin.members.chip_jugend'),
        'offen' => __('members::admin.members.chip_offen'),
        'ausgetreten' => __('members::admin.members.chip_ausgetreten'),
    ];
    $statusLabels = [
        'aktiv' => __('members::admin.members.status_active'),
        'ruhend' => __('members::admin.members.status_ruhend'),
        'ausgetreten' => __('members::admin.members.status_departed'),
    ];
    $dueLabels = [
        'bezahlt' => __('members::admin.dues.status_bezahlt'),
        'ausstehend' => __('members::admin.dues.status_ausstehend'),
        'ueberfaellig' => __('members::admin.dues.status_ueberfaellig'),
    ];
    $memberTones = StatusTone::badgeClasses(StatusTone::MEMBER);
    $dueHeader = $currentFiscalYear
        ? __('members::admin.members.due_column', ['year' => $currentFiscalYear->name])
        : __('members::admin.dues.title');
    $total = $members->total();
    $canEditUsers = Gate::allows('admin.user');
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

{{-- Search and quick-filter chips (server side; chips keep the search and the more filters, the pager keeps everything). --}}
<form method="GET" action="{{ route('members.index') }}" class="mbr-list-filters" role="search">
    @if($filter->chip !== MemberListFilter::DEFAULT)
        <input type="hidden" name="filter" value="{{ $filter->chip }}">
    @endif
    <div class="mbr-search">
        <label for="mi-search" class="sr-only">{{ __('members::admin.members.search_label') }}</label>
        <x-ui.icon name="search" :size="18" class="mbr-search__icon" />
        <input type="search" name="search" id="mi-search" value="{{ $filter->search }}" maxlength="100"
            placeholder="{{ __('members::admin.members.search_placeholder') }}" class="ds-input mbr-search__input">
    </div>
    <div class="mbr-chips" role="group" aria-label="{{ __('members::admin.members.filter_label') }}">
        @foreach([...MemberListFilter::CHIPS, ...($filter->chip === 'ausgetreten' ? ['ausgetreten'] : [])] as $chip)
            @php
                $active = $filter->chip === $chip;
            @endphp
            <x-ui.chip :href="route('members.index', $filter->query(['filter' => $chip]))" :active="$active"
                :title="$chip === 'jugend' ? __('members::admin.members.chip_jugend_hint', ['age' => Member::YOUTH_MAX_AGE]) : null">{{ $active ? __('members::admin.members.chip_count', ['label' => $chipLabels[$chip], 'count' => $total]) : $chipLabels[$chip] }}</x-ui.chip>
        @endforeach
    </div>
    <x-ui.disclosure class="mbr-more-filters" :summary="__('members::admin.common.more_filters')" :open="$filter->hasMoreFilters()">
        <x-ui.field class="mbr-filters__field" :label="__('members::admin.members.fee_category')" for="mi-fee-category">
            <x-ui.select name="fee_category" :options="$feeCategories->pluck('name', 'id')->all()" :selected="(string) ($filter->feeCategory ?? '')" :placeholder="__('members::admin.common.all')" />
        </x-ui.field>
        <x-ui.field class="mbr-filters__field" :label="__('members::admin.dues.title')" for="mi-zahlung">
            <x-ui.select name="zahlung" :options="$dueLabels" :selected="$filter->dueStatus ?? ''" :placeholder="__('members::admin.common.all')" />
        </x-ui.field>
        <x-ui.button type="submit" variant="secondary" :block="false">{{ __('members::admin.common.filter') }}</x-ui.button>
        @if($filter->chip !== 'ausgetreten')
            <a href="{{ route('members.index', $filter->query(['filter' => 'ausgetreten'])) }}" class="mbr-text-link mbr-more-filters__departed">{{ __('members::admin.members.show_departed') }}</a>
        @endif
    </x-ui.disclosure>
</form>

<x-ui.card class="mbr-table-card" padding="none" as="section" aria-label="{{ __('members::admin.members.list_label') }}">
    @if($total === 0)
        <p class="mbr-list-empty">{{ __('members::admin.members.empty') }}</p>
    @else
        {{-- Desktop and tablet: compact table with inline editing; phones get the cards below. --}}
        <div class="mbr-phone-hidden">
        <x-ui.table :caption="__('members::admin.members.title')" :stack="false" class="mbr-list-table">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('members::admin.members.number_short') }}</th>
                    <th scope="col">{{ __('members::admin.members.name') }}</th>
                    <th scope="col">{{ __('members::admin.members.category_short') }}</th>
                    <th scope="col">{{ __('members::admin.members.status') }}</th>
                    <th scope="col">{{ $dueHeader }}</th>
                    <th scope="col" class="mbr-col-wide">{{ __('members::admin.members.phone') }}</th>
                    <th scope="col" class="mbr-col-wide">{{ __('members::admin.members.user_account') }} <span class="mbr-optional-hint text-ink-3 font-normal normal-case tracking-normal">({{ __('members::admin.members.optional') }})</span></th>
                    <th scope="col" class="mbr-cell-open"><span class="sr-only">{{ __('members::admin.common.actions') }}</span></th>
                </tr>
            </x-slot:head>
            @foreach($members as $member)
                @php
                    $due = $member->dues->first();
                @endphp
                <tr>
                    <td class="text-ink-2" data-label="{{ __('members::admin.members.number_short') }}">
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
                        <span x-show="!editing" x-text="lastname + ', ' + firstname" class="mbr-name" :class="{ 'mbr-saved': justSaved }">{{ $member->lastname }}, {{ $member->firstname }}</span>
                        <div x-show="editing" @click.stop class="mbr-name-editor">
                            <div class="flex gap-1">
                                <input type="text" x-model="firstname" @blur="save('firstname')" @keydown.enter.prevent="$event.target.blur()" class="ds-input" :class="{ 'ds-input--invalid': error }" aria-label="{{ __('members::admin.members.firstname') }}">
                                <input type="text" x-model="lastname" @blur="save('lastname')" @keydown.enter.prevent="$event.target.blur()" class="ds-input" :class="{ 'ds-input--invalid': error }" aria-label="{{ __('members::admin.members.lastname') }}">
                            </div>
                            <p class="ds-field__error" x-show="error" x-text="error"></p>
                        </div>
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
                    <td class="cursor-pointer"
                        data-field="status"
                        data-label="{{ __('members::admin.members.status') }}"
                        x-data="{
                            editing: false, error: null, justSaved: false,
                            value: @js($member->status === 'ausgetreten' ? 'aktiv' : $member->status),
                            displayValue: @js($member->status),
                            labels: { aktiv: @js($statusLabels['aktiv']), ruhend: @js($statusLabels['ruhend']), ausgetreten: @js($statusLabels['ausgetreten']) },
                            classes: @js($memberTones),
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
                    <td class="{{ $due ? 'cursor-pointer' : 'text-ink-2' }}"
                        data-field="due-status"
                        data-label="{{ $dueHeader }}"
                        @if($due)
                            x-data="{
                                editing: false, error: null, justSaved: false,
                                value: @js($due->status),
                                displayValue: @js($due->displayStatus()),
                                bezahltBis: @js($due->bezahlt_bis_fiscal_year_id ?? ''),
                                kommentar: @js($due->kommentar ?? ''),
                                labels: { ausstehend: @js($dueLabels['ausstehend']), bezahlt: @js($dueLabels['bezahlt']), ueberfaellig: @js($dueLabels['ueberfaellig']) },
                                classes: @js(StatusTone::badgeClasses(StatusTone::DUE)),
                                async save() {
                                    this.error = null;
                                    const result = await window.saveDueField({{ $due->id }}, 'status', this.value);
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
                                    const result = await window.saveDueField({{ $due->id }}, 'kommentar', this.kommentar || null);
                                    if (!result.ok) { this.error = result.message; }
                                },
                                async savePaidThrough() {
                                    if (!this.bezahltBis) return;
                                    await window.saveDuePaidThrough({{ $due->id }}, this.bezahltBis);
                                }
                            }"
                            @click="if (!editing) editing = true"
                            @click.outside="if (!error) editing = false"
                        @endif
                        >
                        @if($due)
                            <span x-show="!editing" x-text="labels[displayValue]" class="ds-badge" :class="classes[displayValue] + (justSaved ? ' mbr-saved' : '')">{{ $dueLabels[$due->displayStatus()] ?? $due->displayStatus() }}</span>
                            <div x-show="editing" @click.stop class="mbr-due-editor">
                                <select x-model="value" @change="save()" class="ds-select" aria-label="{{ $dueHeader }}">
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
                    <td class="mbr-col-wide text-ink-2 cursor-pointer"
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
                    {{-- Optional link to a booking-system account: informational only, no filter or feature depends on it. --}}
                    <td class="mbr-col-wide text-ink-2" data-label="{{ __('members::admin.members.user_account') }}">
                        @if($member->user_id === null)
                            –
                        @elseif($canEditUsers)
                            <a href="{{ route('admin.users.edit', $member->user_id) }}" class="mbr-row-link">{{ __('members::admin.members.linked') }}</a>
                        @else
                            {{ __('members::admin.members.linked') }}
                        @endif
                    </td>
                    <td class="mbr-cell-open">
                        <a href="{{ route('members.edit', $member) }}" class="mbr-open-link" aria-label="{{ __('members::admin.members.open_member', ['name' => $member->fullName()]) }}"><x-ui.icon name="chevron-right" :size="18" /></a>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        </div>

        {{-- Phones: compact cards that open the member; editing happens on the detail page. --}}
        <ul class="mbr-member-cards mbr-phone-only" aria-label="{{ __('members::admin.members.list_label') }}">
            @foreach($members as $member)
                @php
                    $due = $member->dues->first();
                    $dueOpen = $due !== null && $due->status !== 'bezahlt' && ! $due->payments_exists;
                @endphp
                <li>
                    <a href="{{ route('members.edit', $member) }}" class="mbr-member-card">
                        <span class="mbr-member-card__text">
                            <span class="mbr-member-card__name">{{ $member->lastname }}, {{ $member->firstname }}</span>
                            <span class="mbr-member-card__meta">{{ __('members::admin.members.number_prefix', ['number' => $member->member_number]) }}@if($member->feeCategory) · {{ $member->feeCategory->name }}@endif</span>
                        </span>
                        <span class="mbr-member-card__badges">
                            @if(! $dueOpen || $member->status !== 'aktiv')
                                <span class="ds-badge {{ $memberTones[$member->status] ?? 'ds-badge--neutral' }}">{{ $statusLabels[$member->status] ?? $member->status }}</span>
                            @endif
                            @if($dueOpen)
                                <span class="ds-badge ds-badge--attention">{{ __('members::admin.members.due_open') }}</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="mbr-table-foot mbr-list-foot">
        <span class="mbr-list-foot__hint mbr-phone-hidden">{{ __('members::admin.members.list_hint') }}</span>
        <span>{{ __('members::admin.members.range', ['from' => $members->firstItem() ?? 0, 'to' => $members->lastItem() ?? 0, 'total' => $total]) }}</span>
        <x-ui.pagination :paginator="$members" />
    </div>
</x-ui.card>

@once
    @push('scripts')
        @include('members::partials.inline-save')
    @endpush
@endonce
@endsection
