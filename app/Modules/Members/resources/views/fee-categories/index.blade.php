@extends('layouts.admin')
@section('admin-title', __('members::admin.fee_categories.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header"><h1>{{ __('members::admin.fee_categories.title') }}</h1></div>

    <div class="ui-card">
        <div class="ui-card-header"><h2>{{ __('members::admin.fee_categories.new_category') }}</h2></div>
        <div class="ui-card-body">
            <form method="POST" action="{{ route('admin.members.fee-categories.store') }}" class="ui-row">
                @csrf
                <div class="ui-field min-w-[16rem]">
                    <input type="text" name="name" placeholder="{{ __('members::admin.fee_categories.name') }}" class="ui-input">
                </div>
                <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.save') }}</button>
            </form>
        </div>
    </div>

    <div class="ui-card">
        <div class="ui-card-header">
            <h2>{{ __('members::admin.fee_categories.rates_for') }}</h2>
        </div>
        <div class="ui-card-body">
            <form method="GET" action="{{ route('admin.members.fee-categories.index') }}" class="ui-row">
                <div class="ui-field min-w-[12rem]">
                    <select name="fiscal_year_id" class="ui-select" onchange="this.form.submit()">
                        @foreach($fiscalYears as $fiscalYear)
                            <option value="{{ $fiscalYear->id }}" @selected($selectedFiscalYearId === $fiscalYear->id)>{{ $fiscalYear->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>

            @if($fiscalYears->isNotEmpty())
                <form method="POST" action="{{ route('admin.members.fee-categories.rates.store') }}" class="ui-stack mt-4">
                    @csrf
                    <input type="hidden" name="fiscal_year_id" value="{{ $selectedFiscalYearId }}">
                    <div class="ui-table-wrap">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>{{ __('members::admin.fee_categories.name') }}</th>
                                    <th>{{ __('members::admin.fee_categories.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($categories as $category)
                                    <tr>
                                        <td>{{ $category->name }}</td>
                                        <td>
                                            <input type="number" step="0.01" min="0" name="rates[{{ $category->id }}]" value="{{ $rates[$category->id]->amount ?? '' }}" class="ui-input">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.save') }}</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
