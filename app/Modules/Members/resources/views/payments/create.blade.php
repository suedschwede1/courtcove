@extends('members::layout')
@section('admin-title', __('members::admin.payments.new_payment'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header"><h1>{{ __('members::admin.payments.new_payment') }} — {{ $member->fullName() }}</h1></div>

    <form method="POST" action="{{ route('members.payments.store', $member) }}" class="ui-form-shell">
        @csrf
        <div class="ui-card">
            <div class="ui-card-body ui-stack">
                <div class="ui-grid-3 ui-form-panel">
                    <div class="ui-field">
                        <label class="ui-label" for="pf-amount">{{ __('members::admin.payments.amount') }}</label>
                        <input id="pf-amount" type="number" step="0.01" min="0.01" name="amount" class="ui-input">
                    </div>
                    <div class="ui-field">
                        <label class="ui-label" for="pf-paid-at">{{ __('members::admin.payments.paid_at') }}</label>
                        <input id="pf-paid-at" type="date" name="paid_at" value="{{ now()->toDateString() }}" class="ui-input">
                    </div>
                    <div class="ui-field">
                        <label class="ui-label" for="pf-note">{{ __('members::admin.payments.note') }}</label>
                        <input id="pf-note" type="text" name="note" class="ui-input">
                    </div>
                </div>

                <p class="ui-section-label">{{ __('members::admin.payments.covers') }}</p>
                @foreach($openDues as $due)
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="member_due_ids[]" value="{{ $due->id }}">
                        {{ $due->fiscalYear->name }} — {{ $due->feeCategory->name }} ({{ number_format((float) $due->amount, 2) }} €)
                    </label>
                @endforeach
            </div>
        </div>
        <div class="ui-form-actions">
            <a href="{{ route('members.edit', $member) }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.cancel') }}</a>
            <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.save') }}</button>
        </div>
    </form>
</div>
@endsection
