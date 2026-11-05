@extends('members::layout')
@section('admin-title', $clubRole->name)
@section('members-content')
<x-members::page-header :title="$clubRole->name" />
<form method="POST" action="{{ route('members.functions.update', $clubRole) }}" class="ui-form-shell">
    @csrf
    @method('PUT')
    @include('members::functions._form', ['clubRole' => $clubRole])
    <div class="flex gap-2 justify-end">
        <x-ui.button variant="secondary" :block="false" :href="route('members.functions.index')">{{ __('members::admin.common.cancel') }}</x-ui.button>
        <x-ui.button type="submit" :block="false">{{ __('members::admin.common.save') }}</x-ui.button>
    </div>
</form>
@endsection
