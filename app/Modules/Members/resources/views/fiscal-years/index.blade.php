@extends('members::layout')
@section('admin-title', __('members::admin.fiscal_years.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header flex flex-wrap items-start justify-between gap-4">
        <h1>{{ __('members::admin.fiscal_years.title') }}</h1>
        <a href="{{ route('members.fiscal-years.create') }}" class="ui-btn ui-btn-primary">{{ __('members::admin.fiscal_years.new') }}</a>
    </div>
    <div class="ui-card">
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th>{{ __('members::admin.fiscal_years.name') }}</th>
                        <th>{{ __('members::admin.fiscal_years.starts_on') }}</th>
                        <th>{{ __('members::admin.fiscal_years.ends_on') }}</th>
                        <th>{{ __('members::admin.fiscal_years.due_date') }}</th>
                        <th class="text-right">{{ __('members::admin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fiscalYears as $fiscalYear)
                        <tr>
                            <td class="font-medium">{{ $fiscalYear->name }}</td>
                            <td>{{ $fiscalYear->starts_on->format('d.m.Y') }}</td>
                            <td>{{ $fiscalYear->ends_on->format('d.m.Y') }}</td>
                            <td>{{ optional($fiscalYear->faellig_am)->format('d.m.Y') ?? '–' }}</td>
                            <td class="text-right"><a href="{{ route('members.fiscal-years.edit', $fiscalYear) }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.edit') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
