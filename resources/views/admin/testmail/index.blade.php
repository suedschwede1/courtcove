@extends('layouts.admin')
@section('admin-title', __('booking.admin.testmail.title'))
@section('admin-content')
<div class="ds-admin-stack">
    <x-admin.page-header :title="__('booking.admin.testmail.title')" :eyebrow="__('booking.admin.nav.eyebrow')">
        <p class="ds-admin-note">{{ __('booking.admin.testmail.intro') }}</p>
    </x-admin.page-header>

    <x-ui.card padding="lg" :title="__('booking.admin.testmail.send_title')">
        <form method="POST" action="{{ route('admin.testmail.send') }}" class="ds-admin-stack">
            @csrf
            <x-ui.field :label="__('booking.admin.testmail.recipient')" for="email">
                <x-ui.input type="email" name="email" id="email" :value="old('email', auth()->user()?->email)" required autofocus />
            </x-ui.field>
            <div class="ds-admin-actions">
                <x-ui.button type="submit" :block="false">{{ __('booking.admin.testmail.submit') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
@endsection
