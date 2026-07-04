@extends('members::layout')
@section('admin-title', __('members::admin.dues.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header"><h1>{{ __('members::admin.dues.title') }}</h1></div>

    @if(session('status'))
        <div class="ui-card"><div class="ui-card-body">{{ session('status') }}</div></div>
    @endif

    <div class="ui-card">
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
            <a href="{{ route('members.dues.create') }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.dues.new') }}</a>
        </div>
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
                    </tr>
                </thead>
                <tbody>
                    @foreach($dues as $due)
                        <tr>
                            <td>{{ $due->member->fullName() }}</td>
                            <td>{{ $due->feeCategory->name }}</td>
                            <td>{{ number_format((float) $due->amount, 2) }} €</td>
                            <td>
                                <span class="ui-badge {{ $due->payments->isNotEmpty() ? 'ui-badge-success' : 'ui-badge-info' }}">
                                    {{ $due->payments->isNotEmpty() ? __('members::admin.dues.status_paid') : __('members::admin.dues.status_open') }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
