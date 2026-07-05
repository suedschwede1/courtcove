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
            </div>
        </div>
        <div class="ui-card">
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th style="width:2.5rem"></th>
                            <th>{{ __('members::admin.fiscal_years.title') }}</th>
                            <th>{{ __('members::admin.fee_categories.title') }}</th>
                            <th>{{ __('members::admin.payments.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($openDues as $due)
                            <tr>
                                <td><input type="checkbox" name="member_due_ids[]" value="{{ $due->id }}"></td>
                                <td>{{ $due->fiscalYear->name }}</td>
                                <td>{{ $due->feeCategory->name }}</td>
                                <td>{{ number_format((float) $due->amount, 2) }} €</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="ui-form-actions">
            <a href="{{ route('members.edit', $member) }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.cancel') }}</a>
            <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.save') }}</button>
        </div>
    </form>
</div>
@endsection
