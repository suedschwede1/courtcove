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
</div>
@endsection
