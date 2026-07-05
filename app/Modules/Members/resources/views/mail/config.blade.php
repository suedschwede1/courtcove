@extends('members::layout')
@section('admin-title', __('members::admin.mail.config_title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header"><h1>{{ __('members::admin.mail.config_title') }}</h1></div>

    <div class="ui-card">
        <div class="ui-card-header"><h2>{{ __('members::admin.mail.sender_settings') }}</h2></div>
        <div class="ui-card-body">
            <form method="POST" action="{{ route('members.mail.sender.update') }}" class="ui-stack">
                @csrf
                <div class="ui-field">
                    <label class="ui-label" for="mail-from-name">{{ __('members::admin.mail.from_name') }}</label>
                    <input id="mail-from-name" type="text" name="from_name" value="{{ old('from_name', $fromName) }}" required class="ui-input">
                    @error('from_name')<p class="ui-error">{{ $message }}</p>@enderror
                </div>
                <div class="ui-field">
                    <label class="ui-label" for="mail-from-address">{{ __('members::admin.mail.from_address') }}</label>
                    <input id="mail-from-address" type="email" name="from_address" value="{{ old('from_address', $fromAddress) }}" required class="ui-input">
                    @error('from_address')<p class="ui-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.mail.save_sender') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="ui-card">
        <div class="ui-card-header"><h2>{{ __('members::admin.mail.categories_title') }}</h2></div>
        <div class="ui-card-body ui-stack">
            @forelse($mailCategories as $mailCategory)
                <form method="POST" action="{{ route('members.mail.categories.update', $mailCategory) }}" class="flex items-center gap-2">
                    @csrf
                    @method('PUT')
                    <input type="text" name="name" value="{{ $mailCategory->name }}" class="ui-input flex-1">
                    <button type="submit" class="ui-btn ui-btn-outline">{{ __('members::admin.common.save') }}</button>
                    <button type="submit" form="delete-mail-category-{{ $mailCategory->id }}" class="ui-btn ui-btn-ghost">{{ __('members::admin.common.delete') }}</button>
                </form>
                <form id="delete-mail-category-{{ $mailCategory->id }}" method="POST" action="{{ route('members.mail.categories.destroy', $mailCategory) }}" onsubmit="return confirm({{ Js::from(__('members::admin.mail.confirm_delete_category')) }})" style="display:none">
                    @csrf
                    @method('DELETE')
                </form>
            @empty
                <div class="ui-readonly-box ui-readonly-box-muted">{{ __('members::admin.mail.no_categories') }}</div>
            @endforelse
            <form method="POST" action="{{ route('members.mail.categories.store') }}" class="flex items-center gap-2 pt-2" style="border-top:1px solid var(--ui-border)">
                @csrf
                <input type="text" name="name" placeholder="{{ __('members::admin.mail.new_category_name') }}" class="ui-input flex-1">
                @error('name')<p class="ui-error">{{ $message }}</p>@enderror
                <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.functions.add') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
