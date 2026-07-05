<div class="ui-card">
    <div class="ui-card-body ui-stack">
        <div class="ui-grid-3 ui-form-panel">
            <div class="ui-field">
                <label class="ui-label" for="fy-name">{{ __('members::admin.fiscal_years.name') }}</label>
                <input id="fy-name" type="text" name="name" value="{{ old('name', $fiscalYear->name ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="fy-starts-on">{{ __('members::admin.fiscal_years.starts_on') }}</label>
                <input id="fy-starts-on" type="date" name="starts_on" value="{{ old('starts_on', optional($fiscalYear->starts_on ?? null)->format('Y-m-d')) }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="fy-ends-on">{{ __('members::admin.fiscal_years.ends_on') }}</label>
                <input id="fy-ends-on" type="date" name="ends_on" value="{{ old('ends_on', optional($fiscalYear->ends_on ?? null)->format('Y-m-d')) }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="fy-faellig-am">{{ __('members::admin.fiscal_years.due_date') }}</label>
                <input id="fy-faellig-am" type="date" name="faellig_am" value="{{ old('faellig_am', optional($fiscalYear->faellig_am ?? null)->format('Y-m-d')) }}" class="ui-input">
            </div>
        </div>
    </div>
</div>
