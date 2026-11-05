@php
    $selectedMailCategoryIds = old('mail_category_ids', ($clubRole->mailCategories ?? collect())->pluck('id')->all() ?? []);
@endphp
<x-ui.card>
    <div class="grid gap-4">
        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.field :label="__('members::admin.functions.name')" for="fn-name" :error="$errors->first('name')">
                <x-ui.input name="name" :value="old('name', $clubRole->name ?? '')" />
            </x-ui.field>
            <div class="ds-field">
                <label class="flex items-center gap-2 text-sm" for="fn-is-board">
                    <input id="fn-is-board" type="checkbox" name="is_board" value="1" @checked(old('is_board', $clubRole->is_board ?? false))>
                    {{ __('members::admin.functions.is_board') }}
                </label>
            </div>
        </div>
        @if(($mailCategories ?? collect())->isNotEmpty())
            <fieldset class="ds-field">
                <legend class="ds-field__label">{{ __('members::admin.functions.mail_category') }}</legend>
                <div class="grid gap-2 md:grid-cols-3">
                    @foreach($mailCategories as $mailCategory)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="mail_category_ids[]" value="{{ $mailCategory->id }}" @checked(in_array($mailCategory->id, $selectedMailCategoryIds))>
                            {{ $mailCategory->name }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif
        <x-ui.field :label="__('members::admin.functions.tasks')" for="fn-tasks" :error="$errors->first('tasks')">
            <x-ui.textarea name="tasks" rows="3" :value="old('tasks', $clubRole->tasks ?? '')" />
        </x-ui.field>
    </div>
</x-ui.card>
