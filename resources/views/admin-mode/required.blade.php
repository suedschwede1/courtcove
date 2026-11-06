@extends('layouts.app')
@section('title', __('booking.admin_mode.required_title'))

{{-- Shown by the admin.mode middleware instead of a plain 403 to accounts whose privilege only lacks admin mode. --}}
@section('content')
<div class="mx-auto flex w-full max-w-xl flex-col gap-5 px-4 py-6 md:px-6 md:py-8" data-admin-mode-required>
    <x-ui.page-header
        :eyebrow="__('booking.admin_mode.required_eyebrow')"
        :title="__('booking.admin_mode.required_title')"
        :intro="__('booking.admin_mode.required_intro')" />

    <x-ui.card>
        <form method="POST" action="{{ route('admin-mode.set', ['state' => 'on']) }}" class="flex flex-col gap-4">
            @csrf
            <input type="hidden" name="redirect" value="{{ $intended }}">
            <x-ui.notice tone="neutral">{{ __('booking.admin_mode.required_hint') }}</x-ui.notice>
            <x-ui.button type="submit" icon="settings">{{ __('booking.admin_mode.switch_on') }}</x-ui.button>
            <x-ui.button variant="secondary" :href="route('calendar.index')">{{ __('booking.admin_mode.back_to_calendar') }}</x-ui.button>
        </form>
    </x-ui.card>
</div>
@endsection
