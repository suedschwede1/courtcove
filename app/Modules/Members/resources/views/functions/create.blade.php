@extends('members::layout')
@section('admin-title', __('members::admin.functions.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header"><h1>{{ __('members::admin.functions.new') }}</h1></div>
    <form method="POST" action="{{ route('members.functions.store') }}" class="ui-form-shell">
        @csrf
        @include('members::functions._form')
        <div class="ui-form-actions">
            <a href="{{ route('members.functions.index') }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.cancel') }}</a>
            <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.functions.add') }}</button>
        </div>
    </form>
</div>
@endsection
