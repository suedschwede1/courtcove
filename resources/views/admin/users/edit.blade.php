@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.edit_title'))
@section('admin-back', route('admin.users.index'))
@section('admin-content')
<div class="ds-admin-stack ds-admin-form-page">
    <x-admin.page-header :title="__('booking.admin.users.edit_title')" :eyebrow="__('booking.admin.nav.eyebrow')" :back-url="route('admin.users.index')" :back-label="__('booking.admin.users.title')">
        <p class="ds-admin-note">{{ __('booking.admin.users.intro_edit') }}</p>
        @if($linkedMember !== null)
            <p class="ds-admin-note">{{ __('booking.admin.users.linked_member') }}:
                @can('admin.members')
                    <a href="{{ route('members.edit', $linkedMember) }}" class="ds-admin-link">{{ __('booking.admin.users.linked_member_number', ['number' => $linkedMember->member_number]) }}</a>
                @else
                    {{ __('booking.admin.users.linked_member_number', ['number' => $linkedMember->member_number]) }}
                @endcan
            </p>
        @endif
        <p class="ds-admin-note">{{ __('booking.admin.users.login_history_risk_score') }}: <x-ui.badge :tone="$riskScore > 0 ? 'attention' : 'neutral'">{{ $riskScore }}</x-ui.badge></p>
        <x-slot:actions>
            <x-ui.button :href="route('admin.users.login-history', $user)" variant="secondary" :block="false">{{ __('booking.admin.users.login_history_title') }}</x-ui.button>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="ds-admin-stack">
        @method('PUT')
        @include('admin.users._form', ['privileges' => $privileges, 'user' => $user, 'profile' => $profile, 'granted' => $granted])
        <div class="ds-admin-actions">
            <div class="ds-admin-actions__group ds-admin-actions__group--main">
                <x-ui.button :href="route('admin.users.index')" variant="secondary" :block="false">{{ __('booking.admin.common.abort') }}</x-ui.button>
                <x-ui.button type="submit" :block="false">{{ __('booking.admin.common.save') }}</x-ui.button>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.users.password', $user) }}">
        @csrf
        <x-ui.card :title="__('booking.admin.users.reset_password')" padding="lg" class="ds-admin-form-card">
            <div class="ds-admin-form-grid">
                <x-ui.field :label="__('booking.admin.users.new_password')" for="uf-new-pw">
                    <x-ui.input id="uf-new-pw" name="password" type="password" autocomplete="new-password" />
                </x-ui.field>
            </div>
            <div class="ds-admin-actions__group">
                <x-ui.button type="submit" variant="secondary" :block="false">{{ __('booking.admin.users.reset_password') }}</x-ui.button>
            </div>
        </x-ui.card>
    </form>

    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm({{ Js::from(__('booking.admin.users.confirm_delete')) }})">
        @method('DELETE')
        @csrf
        <x-ui.card :title="__('booking.admin.users.delete_user')" padding="lg" class="ds-admin-form-card">
            <p class="ds-admin-note">{{ __('booking.admin.users.delete_note') }}</p>
            <div class="ds-admin-actions__group">
                <x-ui.button type="submit" variant="danger" :block="false">{{ __('booking.admin.users.delete_user') }}</x-ui.button>
            </div>
        </x-ui.card>
    </form>
</div>
@endsection
