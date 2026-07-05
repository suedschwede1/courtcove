@extends('members::layout')
@section('admin-title', __('members::admin.members.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header">
        <h1>{{ $member->fullName() }}</h1>
    </div>
    <form method="POST" action="{{ route('members.update', $member) }}" class="ui-form-shell">
        @method('PUT')
        @include('members::members._form', ['member' => $member])
        <div class="ui-form-actions">
            <a href="{{ route('members.payments.create', $member) }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.payments.new_payment') }}</a>
            <a href="{{ route('members.index') }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.cancel') }}</a>
            <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.save') }}</button>
        </div>
    </form>

    @include('members::members._history', ['changes' => $changes])
</div>
@endsection
