@extends('members::layout')
@section('admin-title', __('members::admin.fiscal_years.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header"><h1>{{ $fiscalYear->name }}</h1></div>
    <form method="POST" action="{{ route('members.fiscal-years.update', $fiscalYear) }}" class="ui-form-shell">
        @csrf
        @method('PUT')
        @include('members::fiscal-years._form', ['fiscalYear' => $fiscalYear])
        <div class="ui-form-actions">
            <a href="{{ route('members.fiscal-years.index') }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.cancel') }}</a>
            <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.save') }}</button>
        </div>
    </form>
</div>
@endsection
