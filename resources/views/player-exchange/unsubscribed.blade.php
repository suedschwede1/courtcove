@extends('layouts.app')
@section('title', __('booking.account.player_exchange'))

@section('content')
<div class="mx-auto flex w-full max-w-350 flex-col gap-5 px-4 py-6 md:px-6 md:py-8">
    <x-ui.page-header
        :eyebrow="__('booking.account.eyebrow')"
        :title="__('booking.account.player_exchange')" />

    <x-ui.notice tone="good">{{ __('booking.account.player_exchange_unsubscribed') }}</x-ui.notice>
    <p class="ds-field__help">{{ __('booking.account.player_exchange_unsubscribed_hint') }}</p>
</div>
@endsection
