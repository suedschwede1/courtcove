@extends('members::layout')
@section('members-title', __('members::admin.mail.config_title'))
@section('members-content')
<x-members::page-header :title="__('members::admin.mail.config_title')" />

<x-ui.card :title="__('members::admin.mail.sender_settings')" class="mb-4">
    <form method="POST" action="{{ route('members.mail.sender.update') }}" class="grid gap-4">
        @csrf
        <x-ui.field :label="__('members::admin.mail.from_name')" for="mail-from-name" :error="$errors->first('from_name')">
            <x-ui.input name="from_name" :value="old('from_name', $fromName)" required />
        </x-ui.field>
        <x-ui.field :label="__('members::admin.mail.from_address')" for="mail-from-address" :error="$errors->first('from_address')">
            <x-ui.input type="email" name="from_address" :value="old('from_address', $fromAddress)" required />
        </x-ui.field>
        <div>
            <x-ui.button type="submit" :block="false">{{ __('members::admin.mail.save_sender') }}</x-ui.button>
        </div>
    </form>
</x-ui.card>

<x-ui.card :title="__('members::admin.mail.categories_title')">
    <div class="grid gap-3">
        @forelse($mailCategories as $mailCategory)
            <form method="POST" action="{{ route('members.mail.categories.update', $mailCategory) }}" class="flex gap-2">
                @csrf
                @method('PUT')
                <input type="text" name="name" value="{{ $mailCategory->name }}" class="ds-input flex-1" aria-label="{{ $mailCategory->name }}">
                <x-ui.button type="submit" variant="secondary" :block="false">{{ __('members::admin.common.save') }}</x-ui.button>
                <x-ui.button type="submit" variant="danger" :block="false" form="delete-mail-category-{{ $mailCategory->id }}">{{ __('members::admin.common.delete') }}</x-ui.button>
            </form>
            <form id="delete-mail-category-{{ $mailCategory->id }}" method="POST" action="{{ route('members.mail.categories.destroy', $mailCategory) }}" onsubmit="return confirm({{ Js::from(__('members::admin.mail.confirm_delete_category')) }})" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @empty
            <p class="text-sm text-ink-2">{{ __('members::admin.mail.no_categories') }}</p>
        @endforelse
        <form method="POST" action="{{ route('members.mail.categories.store') }}" class="grid gap-2 border-t border-line pt-3">
            @csrf
            <div class="flex gap-2">
                <input type="text" name="name" placeholder="{{ __('members::admin.mail.new_category_name') }}" class="ds-input flex-1" aria-label="{{ __('members::admin.mail.new_category_name') }}">
                <x-ui.button type="submit" :block="false">{{ __('members::admin.functions.add') }}</x-ui.button>
            </div>
            @error('name')<p class="ds-field__error">{{ $message }}</p>@enderror
        </form>
    </div>
</x-ui.card>
@endsection
