@extends('members::layout')
@section('admin-title', __('members::admin.fiscal_years.title'))
@section('admin-content')
<x-members::page-header :title="__('members::admin.fiscal_years.new')" />
<form method="POST" action="{{ route('members.fiscal-years.store') }}" class="ui-form-shell">
    @csrf
    @include('members::fiscal-years._form')
    <div class="flex gap-2 justify-end">
        <x-ui.button variant="secondary" :block="false" :href="route('members.fiscal-years.index')">{{ __('members::admin.common.cancel') }}</x-ui.button>
        <x-ui.button type="submit" :block="false">{{ __('members::admin.common.save') }}</x-ui.button>
    </div>
</form>
@endsection
