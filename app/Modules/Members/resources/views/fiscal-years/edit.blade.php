@extends('members::layout')
@section('admin-title', $fiscalYear->name)
@section('members-content')
<x-members::page-header :title="$fiscalYear->name" />
<form method="POST" action="{{ route('members.fiscal-years.update', $fiscalYear) }}" class="ui-form-shell">
    @csrf
    @method('PUT')
    @include('members::fiscal-years._form', ['fiscalYear' => $fiscalYear])
    <div class="flex gap-2 justify-end">
        <x-ui.button variant="secondary" :block="false" :href="route('members.fiscal-years.index')">{{ __('members::admin.common.cancel') }}</x-ui.button>
        <x-ui.button type="submit" :block="false">{{ __('members::admin.common.save') }}</x-ui.button>
    </div>
</form>
@endsection
