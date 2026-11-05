@extends('members::layout')
@section('members-title', __('members::admin.functions.new'))
@section('members-content')
<x-members::page-header :title="__('members::admin.functions.new')" />
<form method="POST" action="{{ route('members.functions.store') }}" class="ui-form-shell">
    @csrf
    @include('members::functions._form')
    <div class="flex gap-2 justify-end">
        <x-ui.button variant="secondary" :block="false" :href="route('members.functions.index')">{{ __('members::admin.common.cancel') }}</x-ui.button>
        <x-ui.button type="submit" :block="false">{{ __('members::admin.functions.add') }}</x-ui.button>
    </div>
</form>
@endsection
