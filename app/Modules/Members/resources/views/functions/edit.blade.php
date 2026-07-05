@extends('members::layout')
@section('admin-title', __('members::admin.functions.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header"><h1>{{ $clubRole->name }}</h1></div>
    <form method="POST" action="{{ route('members.functions.update', $clubRole) }}" class="ui-form-shell">
        @csrf
        @method('PUT')
        @include('members::functions._form', ['clubRole' => $clubRole])
        <div class="ui-form-actions">
            <a href="{{ route('members.functions.index') }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.cancel') }}</a>
            <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.save') }}</button>
        </div>
    </form>
</div>
@endsection
