@extends('members::layout')
@section('admin-title', __('members::admin.dsgvo.title'))
@section('members-content')
<x-members::page-header :title="__('members::admin.dsgvo.title')" />
<form method="POST" action="{{ route('members.dsgvo.update') }}" class="ui-form-shell">
    @csrf
    <x-ui.card>
        <x-ui.field class="max-w-[16rem]" :label="__('members::admin.dsgvo.retention_years')" for="dsgvo-retention-years" :help="__('members::admin.dsgvo.retention_years_hint')" :error="$errors->first('retention_years')">
            <x-ui.input type="number" min="1" max="100" name="retention_years" required :value="old('retention_years', $retentionYears)" />
        </x-ui.field>
    </x-ui.card>
    <div class="flex gap-2 justify-end">
        <x-ui.button type="submit" :block="false">{{ __('members::admin.dsgvo.save') }}</x-ui.button>
    </div>
</form>
@endsection
