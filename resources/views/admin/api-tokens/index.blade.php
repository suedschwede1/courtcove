@extends('layouts.admin')
@section('admin-title', __('booking.admin.api_tokens.title'))
@section('admin-content')
<div class="ds-admin-stack">
    <x-admin.page-header :title="__('booking.admin.api_tokens.title')" :eyebrow="__('booking.admin.nav.eyebrow')" />

    <x-ui.card padding="lg" :title="__('booking.admin.api_tokens.new_token')">
        <form method="POST" action="{{ route('admin.api-tokens.store') }}" class="ds-admin-filter">
            @csrf
            <x-ui.field :label="__('booking.admin.common.member')" for="token-uid">
                <x-ui.select name="uid" id="token-uid" :options="$users->pluck('alias', 'uid')->all()" :selected="old('uid')" />
            </x-ui.field>
            <x-ui.field :label="__('booking.admin.api_tokens.device_name')" for="token-device">
                <x-ui.input name="device_name" id="token-device" :value="old('device_name')" :placeholder="__('booking.admin.api_tokens.device_name_placeholder')" />
            </x-ui.field>
            <x-ui.button type="submit" :block="false">{{ __('booking.admin.api_tokens.create') }}</x-ui.button>
        </form>
    </x-ui.card>

    @if($tokens->isEmpty())
        <x-ui.card :title="__('booking.admin.api_tokens.title')">
            <p class="ds-admin-note">{{ __('booking.admin.api_tokens.no_tokens') }}</p>
        </x-ui.card>
    @else
        <x-ui.card padding="none" class="ds-admin-rows ds-admin-tokens" :title="__('booking.admin.api_tokens.title')">
            <x-ui.table :caption="__('booking.admin.api_tokens.title')">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('booking.admin.common.member') }}</th>
                        <th scope="col">{{ __('booking.admin.api_tokens.device_name') }}</th>
                        <th scope="col">{{ __('booking.admin.api_tokens.created_at') }}</th>
                        <th scope="col">{{ __('booking.admin.api_tokens.last_used_at') }}</th>
                        <th scope="col" class="ds-admin-actions-cell"><span class="sr-only">{{ __('booking.admin.users.actions') }}</span></th>
                    </tr>
                </x-slot:head>
                @foreach($tokens as $token)
                    <tr>
                        <td class="ds-cell--title" data-label="{{ __('booking.admin.common.member') }}">{{ $token->tokenable?->alias ?? __('booking.admin.api_tokens.unknown_member') }}</td>
                        <td data-label="{{ __('booking.admin.api_tokens.device_name') }}">{{ $token->name }}</td>
                        <td data-label="{{ __('booking.admin.api_tokens.created_at') }}">{{ $token->created_at?->format('d.m.Y H:i') }}</td>
                        <td data-label="{{ __('booking.admin.api_tokens.last_used_at') }}">{{ $token->last_used_at?->format('d.m.Y H:i') ?? __('booking.admin.api_tokens.never_used') }}</td>
                        <td class="ds-admin-actions-cell" data-label="{{ __('booking.admin.users.actions') }}">
                            <form method="POST" action="{{ route('admin.api-tokens.destroy', $token) }}" onsubmit="return confirm({{ Js::from(__('booking.admin.api_tokens.confirm_revoke')) }})">
                                @method('DELETE') @csrf
                                <x-ui.button type="submit" variant="danger" :block="false">{{ __('booking.admin.api_tokens.revoke') }}</x-ui.button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
@endsection
