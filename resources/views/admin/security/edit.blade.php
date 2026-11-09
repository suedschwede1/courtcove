@extends('layouts.admin')
@section('admin-title', __('booking.admin.security.title'))
@section('admin-content')
<div class="ds-admin-stack ds-admin-form-page">
    <x-admin.page-header :title="__('booking.admin.security.title')" :eyebrow="__('booking.admin.nav.eyebrow')">
        <p class="ds-admin-note">{{ __('booking.admin.security.intro', ['days' => $days]) }}</p>
    </x-admin.page-header>

    @error('scope')
        <x-ui.notice tone="problem">{{ $message }}</x-ui.notice>
    @enderror

    <form method="POST" action="{{ route('admin.security.update') }}" class="ds-admin-stack">
        @method('PUT')
        @csrf

        <x-ui.card padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-choice">
                <x-ui.option-group :columns="2" :legend="__('booking.admin.security.legend')" class="ds-admin-flags" aria-describedby="security-help{{ $locked ? ' security-locked' : '' }}">
                    @foreach($scopes as $level)
                        <x-ui.option-tile
                            name="scope"
                            :value="$level"
                            :label="__('booking.admin.security.level.'.$level.'.label')"
                            :description="'– '.__('booking.admin.security.level.'.$level.'.description')"
                            :checked="old('scope', $scope) === $level"
                            :disabled="$locked" />
                    @endforeach
                </x-ui.option-group>
                @if($locked)
                    <p class="ds-field__help" id="security-locked">{{ __('booking.admin.security.from_env') }}</p>
                @endif
                <p class="ds-field__help" id="security-help">{{ __('booking.admin.security.widen_hint') }}</p>
            </div>
            <p class="ds-field__help">{{ __('booking.admin.security.how', ['days' => $days]) }}</p>
        </x-ui.card>

        @unless($locked)
            <div class="ds-admin-actions">
                <x-ui.button type="submit" :block="false">{{ __('booking.admin.security.save') }}</x-ui.button>
            </div>
        @endunless
    </form>

    <x-ui.card :title="__('booking.admin.security.device_title')" padding="lg" class="ds-admin-form-card">
        @if($hasDevice)
            <p class="ds-field__help">{{ __('booking.admin.security.device_ok') }}</p>
        @else
            @if(session('needs_device'))
                <x-ui.notice tone="attention">{{ __('booking.admin.security.device_missing') }}</x-ui.notice>
            @else
                <p class="ds-field__help">{{ __('booking.admin.security.device_missing') }}</p>
            @endif
            <form method="POST" action="{{ route('admin.security.device') }}" class="ds-admin-stack">
                @csrf
                <div class="ds-admin-actions">
                    <x-ui.button type="submit" variant="secondary" :block="false">{{ __('booking.admin.security.confirm_device') }}</x-ui.button>
                </div>
                <p class="ds-field__help">{{ __('booking.admin.security.confirm_device_help') }}</p>
            </form>
        @endif
    </x-ui.card>
</div>
@endsection
