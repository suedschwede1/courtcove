@extends('members::layout')
@section('admin-title', __('members::admin.members.new_member'))
@section('members-content')
<x-members::page-header :title="__('members::admin.members.new_member')" />
<form method="POST" action="{{ route('members.store') }}" class="ui-form-shell">
    @include('members::members._form', ['nextNumber' => $nextNumber])
    <div class="flex gap-2 justify-end">
        <x-ui.button variant="secondary" :block="false" :href="route('members.index')">{{ __('members::admin.common.cancel') }}</x-ui.button>
        <x-ui.button type="submit" :block="false">{{ __('members::admin.common.save') }}</x-ui.button>
    </div>
</form>
@endsection
