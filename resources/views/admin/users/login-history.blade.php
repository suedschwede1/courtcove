@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.login_history_title'))
@section('admin-back', route('admin.users.edit', $user))
@section('admin-content')
@php
    $statusTone = ['success' => 'good', 'failed' => 'problem', 'throttled' => 'attention'];
@endphp
<div class="ds-admin-stack ds-admin-form-page">
    <x-admin.page-header :title="__('booking.admin.users.login_history_title')" :eyebrow="__('booking.admin.nav.eyebrow')" :back-url="route('admin.users.edit', $user)" :back-label="$user->alias">
        <p class="ds-admin-note">{{ $user->alias }} — {{ __('booking.admin.users.login_history_risk_score') }}: {{ $riskScore }}</p>
    </x-admin.page-header>

    @if($history->isEmpty())
        <x-ui.card><p class="ds-admin-note">{{ __('booking.admin.users.login_history_empty') }}</p></x-ui.card>
    @else
        <x-ui.card padding="none" class="ds-admin-rows" :title="__('booking.admin.users.login_history_title')">
            <x-ui.table :caption="__('booking.admin.users.login_history_title')">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('booking.admin.users.login_history_time') }}</th>
                        <th scope="col">{{ __('booking.admin.users.login_history_ip') }}</th>
                        <th scope="col">{{ __('booking.admin.users.login_history_country') }}</th>
                        <th scope="col">{{ __('booking.admin.users.login_history_status') }}</th>
                        <th scope="col">{{ __('booking.admin.users.login_history_flags') }}</th>
                    </tr>
                </x-slot:head>
                @foreach($history as $entry)
                    <tr>
                        <td class="ds-cell--title" data-label="{{ __('booking.admin.users.login_history_time') }}">{{ $entry->created_at?->format('d.m.Y H:i') }}</td>
                        <td data-label="{{ __('booking.admin.users.login_history_ip') }}">{{ $entry->ip }}</td>
                        <td data-label="{{ __('booking.admin.users.login_history_country') }}">{{ $entry->country_code ?? '—' }}</td>
                        <td data-label="{{ __('booking.admin.users.login_history_status') }}">
                            <x-ui.badge :tone="$statusTone[$entry->status] ?? 'neutral'">{{ __('booking.admin.users.login_history_status_'.$entry->status) }}</x-ui.badge>
                        </td>
                        <td data-label="{{ __('booking.admin.users.login_history_flags') }}">
                            @if($entry->risk_flags)
                                {{ collect(explode(',', $entry->risk_flags))->map(fn (string $flag): string => __('booking.admin.users.login_history_flag_'.$flag))->join(', ') }}
                            @else
                                {{ __('booking.admin.users.login_history_flags_none') }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif

    <x-ui.pagination :paginator="$history" />

    <div class="ds-admin-actions">
        <x-ui.button :href="route('admin.users.edit', $user)" variant="secondary" :block="false">{{ __('booking.admin.common.abort') }}</x-ui.button>
    </div>
</div>
@endsection
