@extends('members::layout')
@section('members-title', $household->label())
@section('members-content')
@php
    use App\Modules\Members\Support\MembersFormat;
    use App\Modules\Members\Support\StatusTone;
    use App\Support\Ui\FormValue;

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
    $dissolved = $household->isDissolved();
    $coveredHeader = $fiscalYear
        ? __('members::admin.households.col_covered', ['year' => $fiscalYear->name])
        : __('members::admin.households.col_covered', ['year' => '']);
@endphp
<x-members::page-header :title="$household->label()">
    <x-slot:actions>
        <x-ui.button variant="secondary" :block="false" :href="route('members.households.index')">{{ __('members::admin.households.back') }}</x-ui.button>
        @unless($dissolved)
            <x-ui.button variant="secondary" :block="false" x-data x-on:click="$dispatch('household-dissolve')">{{ __('members::admin.households.dissolve') }}</x-ui.button>
        @endunless
        <x-ui.button type="submit" :block="false" form="household-form" class="mbr-phone-hidden">{{ __('members::admin.households.save') }}</x-ui.button>
    </x-slot:actions>
    <x-slot:phoneAction>
        <x-ui.button type="submit" form="household-form">{{ __('members::admin.households.save') }}</x-ui.button>
    </x-slot:phoneAction>
</x-members::page-header>

@if($dissolved)
    <x-ui.notice tone="neutral">{{ __('members::admin.households.dissolved_at', ['date' => $household->dissolved_at->format('d.m.Y')]) }}</x-ui.notice>
@endif
@if(session('household_hint'))
    <x-ui.notice tone="neutral">{{ session('household_hint') }}</x-ui.notice>
@endif

<div class="mbr-detail">
<div class="mbr-detail__main">
    @if($preview)
        @include('members::households._replace-preview')
    @endif
    <x-ui.card :title="__('members::admin.households.card_master')">
        <form id="household-form" method="POST" action="{{ route('members.households.update', $household->id) }}" class="ui-form-shell">
            @csrf
            @method('PUT')
            <x-ui.field :label="__('members::admin.households.name')" for="name" :help="__('members::admin.households.name_help')" :error="$errors->first('name')">
                <x-ui.input maxlength="128" :value="FormValue::string(old('name', $household->name))" />
            </x-ui.field>
            <x-ui.field :label="__('members::admin.households.tariff')" for="fee_category_id" :help="__('members::admin.households.tariff_help')" :error="$errors->first('fee_category_id')">
                <x-ui.select :options="$tariffs->mapWithKeys(fn ($c) => [$c->id => $c->labelWithState()])->all()" :selected="FormValue::string(old('fee_category_id', $household->fee_category_id))" :placeholder="__('members::admin.households.tariff_none')" />
            </x-ui.field>
            <x-ui.field :label="__('members::admin.households.payer')" for="payer_member_id" :help="__('members::admin.households.payer_help')" :error="$errors->first('payer_member_id')">
                <x-ui.select :options="$household->members->mapWithKeys(fn ($m) => [$m->id => $m->fullName()])->all()" :selected="FormValue::string(old('payer_member_id', $household->payer_member_id))" :placeholder="__('members::admin.households.payer_none')" />
            </x-ui.field>
        </form>
    </x-ui.card>

    {{-- Own dues of members in the current year (decision 1): unpaid ones can be replaced, paid ones only get a hint. --}}
    @foreach($ownDues as $ownDue)
        @php $hintData = ['name' => $ownDue->member->fullName(), 'year' => $fiscalYear?->name, 'amount' => MembersFormat::money($ownDue->amount)]; @endphp
        @if($ownDue->isReplaceable())
            <x-ui.notice tone="neutral">
                <p class="m-0">{{ __('members::admin.households.replace.hint_replaceable', $hintData) }}</p>
                @if(! $dissolved && $household->feeCategory?->isPerHousehold() && ! ($preview && $previewYear?->id === $fiscalYear?->id))
                    <div class="mt-3">
                        <x-ui.button variant="secondary" :block="false" :href="route('members.households.replace-dues.preview', ['household' => $household->id, 'fiscal_year_id' => $fiscalYear?->id])">{{ __('members::admin.households.replace.button') }}</x-ui.button>
                    </div>
                @endif
            </x-ui.notice>
        @else
            <x-ui.notice tone="neutral">{{ __('members::admin.households.replace.hint_paid', $hintData) }}</x-ui.notice>
        @endif
    @endforeach

    <x-ui.card class="mbr-table-card" padding="none" :title="__('members::admin.households.card_members')">
        @if($household->members->isEmpty())
            <p class="mbr-list-empty">{{ __('members::admin.households.no_members') }}</p>
        @else
            <x-ui.table :caption="__('members::admin.households.card_members')">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('members::admin.households.col_number') }}</th>
                        <th scope="col">{{ __('members::admin.households.col_name') }}</th>
                        <th scope="col">{{ __('members::admin.households.col_category') }}</th>
                        <th scope="col">{{ __('members::admin.households.col_status') }}</th>
                        <th scope="col">{{ $coveredHeader }}</th>
                        <th scope="col"><span class="sr-only">{{ __('members::admin.common.actions') }}</span></th>
                    </tr>
                </x-slot:head>
                @foreach($household->members as $member)
                    @php $covered = in_array($member->id, $coveredIds, true); @endphp
                    <tr>
                        <td class="text-ink-2" data-label="{{ __('members::admin.households.col_number') }}">{{ $member->member_number }}</td>
                        <td class="ds-cell--title" data-label="{{ __('members::admin.households.col_name') }}">
                            <a href="{{ route('members.edit', $member) }}" class="mbr-row-link">{{ $member->fullName() }}</a>
                            @if($member->id === $household->payer_member_id)
                                <span class="ds-badge ds-badge--neutral" title="{{ __('members::admin.households.payer') }}">{{ __('members::admin.households.payer_marker') }}</span>
                            @endif
                        </td>
                        <td class="text-ink-2" data-label="{{ __('members::admin.households.col_category') }}">{{ $member->feeCategory->name ?? '–' }}</td>
                        <td data-label="{{ __('members::admin.households.col_status') }}">
                            <x-ui.badge :tone="StatusTone::member($member->status)">{{ $statusLabels[$member->status] ?? $member->status }}</x-ui.badge>
                        </td>
                        <td data-label="{{ $coveredHeader }}" @if($covered) data-covered="{{ $member->id }}" @endif>
                            @if($covered)
                                <x-ui.icon name="check" :size="18" /><span class="sr-only">{{ __('members::admin.households.covered_yes') }}</span>
                            @else
                                <span aria-hidden="true">–</span><span class="sr-only">{{ __('members::admin.households.covered_no') }}</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('members.households.members.remove', ['household' => $household->id, 'member' => $member->id]) }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button type="submit" variant="secondary" :block="false" aria-label="{{ __('members::admin.households.remove_label', ['name' => $member->fullName()]) }}">{{ __('members::admin.households.remove') }}</x-ui.button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
        @unless($dissolved)
            <form method="POST" action="{{ route('members.households.members.add', $household->id) }}" class="mbr-filters mbr-compose">
                @csrf
                <x-ui.field class="mbr-filters__search" :label="__('members::admin.households.add_member')" for="member_id" :error="$errors->first('member_id')">
                    <select name="member_id" id="hh-add-member" class="ds-select{{ $errors->has('member_id') ? ' ds-select--invalid' : '' }}" required>
                        <option value="">{{ __('members::admin.households.add_placeholder') }}</option>
                        @foreach($candidates as $candidate)
                            @if($candidate->household_id !== null)
                                <option value="{{ $candidate->id }}" disabled>{{ __('members::admin.households.in_other_household', ['name' => $candidate->fullName(), 'household' => $candidate->household?->label() ?? '']) }}</option>
                            @else
                                <option value="{{ $candidate->id }}">{{ $candidate->lastname }}, {{ $candidate->firstname }} ({{ __('members::admin.members.number_prefix', ['number' => $candidate->member_number]) }})</option>
                            @endif
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.button type="submit" variant="secondary" :block="false" icon="plus">{{ __('members::admin.households.add') }}</x-ui.button>
            </form>
        @endunless
    </x-ui.card>
</div>

<div class="mbr-detail__side">
    <x-ui.card class="mbr-table-card" padding="none" :title="__('members::admin.households.card_dues')">
        @if($dues->isEmpty())
            <p class="mbr-list-empty">{{ __('members::admin.households.no_dues') }}</p>
        @else
            <x-ui.table :caption="__('members::admin.households.card_dues')">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('members::admin.dues.fiscal_year') }}</th>
                        <th scope="col">{{ __('members::admin.dues.amount') }}</th>
                        <th scope="col">{{ __('members::admin.dues.status') }}</th>
                    </tr>
                </x-slot:head>
                @foreach($dues as $due)
                    @php $status = $due->payments_exists ? 'bezahlt' : $due->displayStatus(); @endphp
                    <tr>
                        <td class="ds-cell--title" data-label="{{ __('members::admin.dues.fiscal_year') }}">{{ $due->fiscalYear->name }}</td>
                        <td class="tabular-nums whitespace-nowrap" data-label="{{ __('members::admin.dues.amount') }}">{{ MembersFormat::money($due->amount) }}</td>
                        <td data-label="{{ __('members::admin.dues.status') }}"><x-ui.badge :tone="StatusTone::due($status)">{{ $dueLabels[$status] ?? $status }}</x-ui.badge></td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
        @if(! $dissolved && $household->feeCategory?->isPerHousehold() && $openYears->isNotEmpty())
            {{-- Manual household due: amount empty = the tariff's rate for the year. --}}
            <form method="POST" action="{{ route('members.household-dues.store') }}" class="mbr-filters mbr-compose">
                @csrf
                <input type="hidden" name="household_id" value="{{ $household->id }}">
                <x-ui.field class="mbr-filters__field" :label="__('members::admin.dues.fiscal_year')" for="fiscal_year_id" :error="$errors->first('fiscal_year_id') ?: $errors->first('household_id')">
                    <x-ui.select :options="$openYears->pluck('name', 'id')->all()" :selected="FormValue::string(old('fiscal_year_id', $fiscalYear?->id))" />
                </x-ui.field>
                <x-ui.field class="mbr-filters__field" :label="__('members::admin.households.due_amount')" for="amount" :help="__('members::admin.households.due_amount_help')" :error="$errors->first('amount')">
                    <x-ui.input type="number" step="0.01" min="0" max="999999.99" inputmode="decimal" :value="FormValue::string(old('amount'))" />
                </x-ui.field>
                <x-ui.button type="submit" variant="secondary" :block="false" icon="plus">{{ __('members::admin.households.due_create') }}</x-ui.button>
            </form>
        @endif
    </x-ui.card>
</div>
</div>

@unless($dissolved)
    {{-- The header button dispatches household-dissolve; existing dues and coverage stay (decision 7), no delete option. --}}
    <div x-data="{ dissolving: false }" x-on:household-dissolve.window="dissolving = true">
    <x-ui.modal id="household-dissolve" show="dissolving" :label="__('members::admin.households.dissolve_title')">
        <x-slot:title>{{ __('members::admin.households.dissolve_title') }}</x-slot:title>
        <form method="POST" id="household-dissolve-form" action="{{ route('members.households.dissolve', $household->id) }}">
            @csrf
            <p class="m-0 text-ink">{{ __('members::admin.households.dissolve_text') }}</p>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" :block="false" x-on:click="dissolving = false">{{ __('members::admin.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="household-dissolve-form" variant="danger" :block="false" data-modal-initial>{{ __('members::admin.households.dissolve_confirm') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
    </div>
@endunless
@endsection
