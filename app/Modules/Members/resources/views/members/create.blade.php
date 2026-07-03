@extends('members::layout')
@section('admin-title', __('members::admin.members.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header">
        <h1>{{ __('members::admin.members.new_member') }}</h1>
    </div>
    <form method="POST" action="{{ route('members.store') }}" class="ui-form-shell">
        @include('members::members._form', ['nextNumber' => $nextNumber])
        <div class="ui-form-actions">
            <a href="{{ route('members.index') }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.cancel') }}</a>
            <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.save') }}</button>
        </div>
    </form>
</div>
@endsection
