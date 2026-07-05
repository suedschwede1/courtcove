<div class="ui-card">
    <div class="ui-card-body ui-stack">
        <div class="ui-grid-3 ui-form-panel">
            <div class="ui-field">
                <label class="ui-label" for="fn-name">{{ __('members::admin.functions.name') }}</label>
                <input id="fn-name" type="text" name="name" value="{{ old('name', $clubRole->name ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="fn-is-board">
                    <input id="fn-is-board" type="checkbox" name="is_board" value="1" @checked(old('is_board', $clubRole->is_board ?? false))>
                    {{ __('members::admin.functions.is_board') }}
                </label>
            </div>
        </div>
        <div class="ui-field">
            <label class="ui-label" for="fn-tasks">{{ __('members::admin.functions.tasks') }}</label>
            <textarea id="fn-tasks" name="tasks" rows="3" class="ui-input">{{ old('tasks', $clubRole->tasks ?? '') }}</textarea>
        </div>
    </div>
</div>
