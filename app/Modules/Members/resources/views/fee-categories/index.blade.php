@extends('members::layout')
@section('members-title', __('members::admin.fee_categories.title'))
@section('members-content')
{{--
    Beitragskategorien: every category with its rates per fiscal year and an edit link (also without a fiscal
    year or rates), the form for a new category, and the rate matrix for one fiscal year. Without a fiscal year
    the matrix card explains that one is needed first and links to creating it.
--}}
@php($money = fn ($amount) => \App\Modules\Members\Support\MembersFormat::money($amount))
@php($selectedYearClosed = (bool) $fiscalYears->firstWhere('id', $selectedFiscalYearId)?->isClosed())
<x-members::page-header :title="__('members::admin.fee_categories.title')" />

@if($categories->isEmpty())
    <x-ui.card :title="__('members::admin.fee_categories.list')">
        <p class="m-0 text-body-s text-ink-2">{{ __('members::admin.fee_categories.empty') }}</p>
    </x-ui.card>
@else
    <x-ui.card class="mbr-table-card" padding="none" as="div">
        <x-ui.table :caption="__('members::admin.fee_categories.list')">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('members::admin.fee_categories.name') }}</th>
                    <th scope="col">{{ __('members::admin.fee_categories.rates') }}</th>
                    <th scope="col" class="mbr-cell-actions">{{ __('members::admin.common.actions') }}</th>
                </tr>
            </x-slot:head>
            @foreach($categories as $category)
                @php($categoryRates = $category->rates->keyBy('fiscal_year_id'))
                <tr>
                    <td class="ds-cell--title" data-label="{{ __('members::admin.fee_categories.name') }}">{{ $category->name }}</td>
                    <td data-label="{{ __('members::admin.fee_categories.rates') }}">
                        <div class="flex flex-wrap gap-2">
                            @forelse($fiscalYears->filter(fn ($fiscalYear) => $categoryRates->has($fiscalYear->id)) as $fiscalYear)
                                <x-ui.badge tone="neutral" class="tabular-nums">{{ $fiscalYear->name }}: {{ $money($categoryRates[$fiscalYear->id]->amount) }}</x-ui.badge>
                            @empty
                                <x-ui.badge tone="attention">{{ __('members::admin.fee_categories.no_rate') }}</x-ui.badge>
                            @endforelse
                        </div>
                    </td>
                    <td class="mbr-cell-actions" data-label="{{ __('members::admin.common.actions') }}">
                        <x-ui.button variant="secondary" :block="false" :href="route('members.fee-categories.edit', $category)">{{ __('members::admin.common.edit') }}</x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>
@endif

<x-ui.card :title="__('members::admin.fee_categories.new_category')">
    <form method="POST" action="{{ route('members.fee-categories.store') }}" class="mbr-inline-form">
        @csrf
        <x-ui.field class="mbr-inline-form__field" :label="__('members::admin.fee_categories.name')" for="fc-name">
            <x-ui.input name="name" id="fc-name" maxlength="64" required :value="is_string(old('name')) ? old('name') : ''" />
        </x-ui.field>
        <x-ui.button type="submit" :block="false">{{ __('members::admin.common.save') }}</x-ui.button>
    </form>
</x-ui.card>

<x-ui.card :title="__('members::admin.fee_categories.rates_for')">
    @if($fiscalYears->isEmpty())
        <x-ui.notice tone="neutral">
            <p class="m-0">{{ __('members::admin.fee_categories.no_fiscal_year') }}</p>
            <div class="mt-3">
                <x-ui.button variant="secondary" :block="false" icon="plus" :href="route('members.fiscal-years.create')">{{ __('members::admin.fee_categories.create_fiscal_year_first') }}</x-ui.button>
            </div>
        </x-ui.notice>
    @else
        <form method="GET" action="{{ route('members.fee-categories.index') }}" class="mbr-inline-form">
            <x-ui.field class="mbr-inline-form__field" :label="__('members::admin.fee_categories.rates_for')" for="fc-fiscal-year">
                <x-ui.select name="fiscal_year_id" id="fc-fiscal-year" :options="$fiscalYears->mapWithKeys(fn ($fy) => [$fy->id => $fy->labelWithState()])->all()" :selected="$selectedFiscalYearId" onchange="this.form.submit()" />
            </x-ui.field>
        </form>

        @if($categories->isNotEmpty())
            <form method="POST" action="{{ route('members.fee-categories.rates.store') }}" class="mbr-rates-form">
                @csrf
                <input type="hidden" name="fiscal_year_id" value="{{ $selectedFiscalYearId }}">
                <x-ui.table :caption="__('members::admin.fee_categories.rates_for')">
                    <x-slot:head>
                        <tr>
                            <th scope="col">{{ __('members::admin.fee_categories.name') }}</th>
                            <th scope="col">{{ __('members::admin.fee_categories.amount') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach($categories as $category)
                        <tr>
                            <td class="ds-cell--title" data-label="{{ __('members::admin.fee_categories.name') }}">{{ $category->name }}</td>
                            <td data-label="{{ __('members::admin.fee_categories.amount') }}">
                                <x-ui.input type="number" step="0.01" min="0" max="999999.99" name="rates[{{ $category->id }}]" id="fc-rate-{{ $category->id }}" :value="$rates[$category->id]->amount ?? null" aria-label="{{ $category->name }}" :disabled="$selectedYearClosed" />
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
                @if($selectedYearClosed)
                    <p class="m-0 text-body-s text-ink-2">{{ __('members::admin.fiscal_years.closed_help') }}</p>
                @else
                    <div><x-ui.button type="submit" :block="false">{{ __('members::admin.common.save') }}</x-ui.button></div>
                @endif
            </form>
        @endif
    @endif
</x-ui.card>
@endsection
