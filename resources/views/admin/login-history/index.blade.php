@extends('layouts.admin')
@section('admin-title', __('booking.admin.login_history.title'))
@section('admin-content')
@php
    $statusTone = ['success' => 'good', 'failed' => 'problem', 'throttled' => 'attention'];
    $statusOptions = [];
    foreach (['success', 'failed', 'throttled'] as $status) {
        $statusOptions[$status] = __('booking.admin.users.login_history_status_'.$status);
    }
@endphp
<div class="ds-admin-stack">
    <x-admin.page-header :title="__('booking.admin.login_history.title')" :eyebrow="__('booking.admin.nav.eyebrow')">
        <p class="ds-admin-note">{{ __('booking.admin.login_history.intro') }}</p>
    </x-admin.page-header>

    <x-ui.card padding="lg">
        <form method="GET" action="{{ route('admin.login-history.index') }}" class="ds-admin-filter">
            <x-ui.field :label="__('booking.admin.login_history.filter_user')" for="lh-user" class="ds-admin-filter__wide">
                <x-ui.input name="user" id="lh-user" :value="$filters['user'] ?? ''" />
            </x-ui.field>
            <x-ui.field :label="__('booking.admin.users.login_history_status')" for="lh-status">
                <x-ui.select name="status" id="lh-status" :options="$statusOptions" :selected="$filters['status'] ?? ''" :placeholder="__('booking.admin.search_status_all')" />
            </x-ui.field>
            <x-ui.field :label="__('booking.admin.users.login_history_country')" for="lh-country">
                <x-ui.input name="country" id="lh-country" maxlength="2" :value="$filters['country'] ?? ''" />
            </x-ui.field>
            <x-ui.field :label="__('booking.admin.login_history.filter_date_from')" for="lh-date-from">
                <x-ui.input name="date_from" id="lh-date-from" type="date" :value="$filters['date_from'] ?? ''" />
            </x-ui.field>
            <x-ui.field :label="__('booking.admin.login_history.filter_date_to')" for="lh-date-to">
                <x-ui.input name="date_to" id="lh-date-to" type="date" :value="$filters['date_to'] ?? ''" />
            </x-ui.field>
            <x-ui.button type="submit" :block="false">{{ __('booking.admin.common.filter') }}</x-ui.button>
        </form>
    </x-ui.card>

    <div class="ds-admin-kpis">
        <x-ui.card as="div" class="ds-admin-kpi">
            <x-ui.kpi :label="__('booking.admin.login_history.stat_success')" :value="$statusCounts['success'] ?? 0" />
        </x-ui.card>
        <x-ui.card as="div" class="ds-admin-kpi">
            <x-ui.kpi :label="__('booking.admin.login_history.stat_failed')" :value="($statusCounts['failed'] ?? 0) + ($statusCounts['throttled'] ?? 0)" :meta="($statusCounts['failed'] ?? 0).' '.__('booking.admin.users.login_history_status_failed').' · '.($statusCounts['throttled'] ?? 0).' '.__('booking.admin.users.login_history_status_throttled')" />
        </x-ui.card>
        <x-ui.card as="div" class="ds-admin-kpi">
            <x-ui.kpi :label="__('booking.admin.login_history.stat_today')" :value="$todayCount" />
        </x-ui.card>
        <x-ui.card as="div" class="ds-admin-kpi">
            <x-ui.kpi :label="__('booking.admin.login_history.stat_month')" :value="$monthCount" />
        </x-ui.card>
    </div>

    <x-ui.card :title="__('booking.admin.login_history.stat_countries')">
        @if($countryCounts->isEmpty())
            <p class="ds-admin-note">{{ __('booking.admin.users.login_history_empty') }}</p>
        @else
            <div class="ds-admin-badges">
                @foreach($countryCounts as $country => $count)
                    <x-ui.badge tone="neutral">{{ $country }}: {{ $count }}</x-ui.badge>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    @if($history->isEmpty())
        <x-ui.card><p class="ds-admin-note">{{ __('booking.admin.users.login_history_empty') }}</p></x-ui.card>
    @else
        <x-ui.card padding="none" class="ds-admin-rows" :title="__('booking.admin.login_history.title')">
            <x-ui.table :caption="__('booking.admin.login_history.title')">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('booking.admin.login_history.user') }}</th>
                        <th scope="col">{{ __('booking.admin.users.login_history_time') }}</th>
                        <th scope="col">{{ __('booking.admin.users.login_history_ip') }}</th>
                        <th scope="col">{{ __('booking.admin.users.login_history_country') }}</th>
                        <th scope="col">{{ __('booking.admin.users.login_history_status') }}</th>
                        <th scope="col">{{ __('booking.admin.users.login_history_flags') }}</th>
                    </tr>
                </x-slot:head>
                @foreach($history as $entry)
                    <tr>
                        <td class="ds-cell--title" data-label="{{ __('booking.admin.login_history.user') }}">
                            @if($entry->user)
                                <a href="{{ route('admin.users.edit', $entry->user) }}" class="ds-admin-link">{{ $entry->user->alias }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td data-label="{{ __('booking.admin.users.login_history_time') }}">{{ $entry->created_at?->format('d.m.Y H:i') }}</td>
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
</div>
@endsection
