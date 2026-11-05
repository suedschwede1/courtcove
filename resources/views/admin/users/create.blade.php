@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.create_title'))
@section('admin-back', route('admin.users.index'))
@section('admin-content')
<div class="ds-admin-stack ds-admin-form-page">
    <x-admin.page-header :title="__('booking.admin.users.create_title')" :eyebrow="__('booking.admin.nav.eyebrow')" :back-url="route('admin.users.index')" :back-label="__('booking.admin.users.title')">
        <p class="ds-admin-note">{{ __('booking.admin.users.intro_create') }}</p>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.users.store') }}" class="ds-admin-stack">
        @include('admin.users._form', ['privileges' => $privileges])
        <div class="ds-admin-actions">
            <div class="ds-admin-actions__group ds-admin-actions__group--main">
                <x-ui.button :href="route('admin.users.index')" variant="secondary" :block="false">{{ __('booking.admin.common.abort') }}</x-ui.button>
                <x-ui.button type="submit" :block="false">{{ __('booking.admin.common.create') }}</x-ui.button>
            </div>
        </div>
    </form>
</div>
@endsection
