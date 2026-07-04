@extends('members::layout')
@section('admin-title', __('members::admin.dues.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header"><h1>{{ __('members::admin.dues.new') }}</h1></div>

    <form method="POST" action="{{ route('members.dues.store') }}" class="ui-form-shell">
        @csrf
        <div class="ui-card">
            <div class="ui-card-body ui-stack">
                <div class="ui-grid-3 ui-form-panel">
                    <div class="ui-field">
                        <label class="ui-label" for="df-member">{{ __('members::admin.dues.member') }}</label>
                        <select id="df-member" name="member_id" class="ui-select">
                            @foreach($members as $member)
                                <option value="{{ $member->id }}" data-fee-category="{{ $member->fee_category_id }}" @selected((int) old('member_id') === $member->id)>{{ $member->fullName() }}</option>
                            @endforeach
                        </select>
                        @error('member_id')<p class="ui-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="ui-field">
                        <label class="ui-label" for="df-fiscal-year">{{ __('members::admin.fiscal_years.title') }}</label>
                        <select id="df-fiscal-year" name="fiscal_year_id" class="ui-select">
                            @foreach($fiscalYears as $fiscalYear)
                                <option value="{{ $fiscalYear->id }}" @selected((int) old('fiscal_year_id') === $fiscalYear->id)>{{ $fiscalYear->name }}</option>
                            @endforeach
                        </select>
                        @error('fiscal_year_id')<p class="ui-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="ui-field">
                        <label class="ui-label" for="df-category">{{ __('members::admin.fee_categories.title') }}</label>
                        <select id="df-category" name="fee_category_id" class="ui-select">
                            @foreach($feeCategories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('fee_category_id') === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('fee_category_id')<p class="ui-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="ui-field">
                        <label class="ui-label" for="df-amount">{{ __('members::admin.fee_categories.amount') }}</label>
                        <input id="df-amount" type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" class="ui-input">
                        @error('amount')<p class="ui-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </div>
        <div class="ui-form-actions">
            <a href="{{ route('members.dues.index') }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.cancel') }}</a>
            <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.common.save') }}</button>
        </div>
    </form>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const memberSelect = document.getElementById('df-member');
        const categorySelect = document.getElementById('df-category');
        const sync = () => {
            const category = memberSelect.selectedOptions[0]?.dataset.feeCategory;
            categorySelect.value = category || categorySelect.options[0]?.value || '';
        };
        memberSelect.addEventListener('change', sync);
        @unless(old('fee_category_id'))
        sync();
        @endunless
    });
</script>
@endsection
