@extends('members::layout')
@section('members-title', __('members::admin.fee_categories.edit_title'))
@section('members-content')
{{--
    Edit one Beitragskategorie: its name and one rate per fiscal year (an empty field keeps the stored rate).
    Without a fiscal year the rates card explains that one is needed first and links to creating it.
--}}
<x-members::page-header :title="__('members::admin.fee_categories.edit_title')" />
<form method="POST" action="{{ route('members.fee-categories.update', $category) }}" class="ui-form-shell">
    @csrf
    @method('PUT')
    <x-ui.card>
        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.field :label="__('members::admin.fee_categories.name')" for="fc-name" :error="$errors->first('name')">
                <x-ui.input name="name" id="fc-name" maxlength="64" required :value="is_string(old('name')) ? old('name') : $category->name" />
            </x-ui.field>
        </div>
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
            @if($errors->has('rates'))
                <p class="ds-field__error">{{ $errors->first('rates') }}</p>
            @endif
            <div class="grid gap-4 md:grid-cols-2">
                @foreach($fiscalYears as $fiscalYear)
                    @php($oldRate = old('rates.'.$fiscalYear->id))
                    <x-ui.field :label="$fiscalYear->labelWithState().' · '.__('members::admin.fee_categories.amount')" for="fc-rate-{{ $fiscalYear->id }}" :error="$errors->first('rates.'.$fiscalYear->id)">
                        <x-ui.input type="number" step="0.01" min="0" max="999999.99" inputmode="decimal" name="rates[{{ $fiscalYear->id }}]" id="fc-rate-{{ $fiscalYear->id }}" :value="is_scalar($oldRate) ? $oldRate : ($rates[$fiscalYear->id]->amount ?? null)" :placeholder="isset($rates[$fiscalYear->id]) ? null : __('members::admin.fee_categories.no_rate')" :disabled="$fiscalYear->isClosed()" />
                    </x-ui.field>
                @endforeach
            </div>
            <p class="mt-3 m-0 text-body-s text-ink-2">{{ __('members::admin.fee_categories.rate_keep_hint') }}</p>
        @endif
    </x-ui.card>

    <div class="flex gap-2 justify-end">
        <x-ui.button variant="secondary" :block="false" :href="route('members.fee-categories.index')">{{ __('members::admin.common.cancel') }}</x-ui.button>
        <x-ui.button type="submit" :block="false">{{ __('members::admin.common.save') }}</x-ui.button>
    </div>
</form>

{{-- Deactivate instead of delete; a separate form because forms cannot be nested. --}}
<x-ui.card :title="__('members::admin.fee_categories.state_card')">
    <div class="flex flex-col items-start gap-3">
        @if($category->isDeactivated())
            <x-ui.badge tone="neutral">{{ __('members::admin.fee_categories.deactivated') }}</x-ui.badge>
        @endif
        <p class="m-0 text-body-s text-ink-2">{{ __('members::admin.fee_categories.deactivate_help') }}</p>
        @include('members::fee-categories._toggle', ['category' => $category])
    </div>
</x-ui.card>
@endsection
