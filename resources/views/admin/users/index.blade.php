@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.title'))
@section('admin-content')
@php
    $statusOptions = [];
    foreach (\App\Models\User::STATUSES as $val) {
        $statusOptions[$val] = __('booking.admin.users.status_'.$val);
    }
    $roleOptions = [];
    foreach (\App\Models\User::ROLES as $val) {
        $roleOptions[$val] = __('booking.admin.users.role_'.$val);
    }
@endphp
<div class="ds-admin-stack" x-data="{ selected: [] }">
    <x-admin.page-header :title="__('booking.admin.users.title')" :eyebrow="__('booking.admin.nav.eyebrow')">
        <p class="ds-admin-note">{{ __('booking.admin.users.intro_index') }}</p>
        <x-slot:actions>
            <x-ui.button :href="route('admin.users.create')" icon="plus" :block="false" class="ds-admin-fab">{{ __('booking.admin.users.new_user') }}</x-ui.button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-ui.card padding="lg">
        <form method="GET" action="{{ route('admin.users.index') }}" class="ds-admin-filter">
            <x-ui.field :label="__('booking.admin.search_placeholder')" for="users-q" class="ds-admin-filter__wide">
                <x-ui.input name="q" id="users-q" type="search" :value="$filters['q'] ?? ''" :placeholder="__('booking.admin.search_placeholder')" />
            </x-ui.field>
            <x-ui.field :label="__('booking.admin.users.status')" for="users-status">
                <x-ui.select name="status" id="users-status" :options="$statusOptions" :selected="$filters['status'] ?? ''" :placeholder="__('booking.admin.search_status_all')" />
            </x-ui.field>
            <x-ui.field :label="__('booking.admin.users.role')" for="users-role">
                <x-ui.select name="role" id="users-role" :options="$roleOptions" :selected="$filters['role'] ?? ''" :placeholder="__('booking.admin.users.role_all')" />
            </x-ui.field>
            <x-ui.button type="submit" :block="false">{{ __('booking.admin.common.filter') }}</x-ui.button>
        </form>
    </x-ui.card>

    @if($searched)
        @if($users->isEmpty())
            <x-ui.card :title="__('booking.admin.users.members')">
                <p class="ds-admin-note">{{ __('booking.admin.no_results') }}</p>
            </x-ui.card>
        @else
            <x-ui.card padding="none" class="ds-admin-rows ds-admin-users" :title="__('booking.admin.users.members')">
                <x-slot:actions>
                    <span class="ds-admin-note">{{ __('booking.admin.users.results_count', ['count' => $users->count()]) }}</span>
                </x-slot:actions>

                <div x-show="selected.length > 0" x-cloak class="ds-admin-bulk">
                    <span class="ds-admin-bulk__count" x-text="{{ Js::from(__('booking.admin.users.selected_count')) }}.replace(':count', selected.length)"></span>
                    <form method="POST" action="{{ route('admin.users.bulk') }}" class="ds-admin-bulk__actions">
                        @csrf
                        <template x-for="uid in selected" :key="uid">
                            <input type="hidden" name="uids[]" :value="uid">
                        </template>
                        <x-ui.button type="submit" variant="danger" :block="false" name="action" value="blocked" onclick="return confirm({{ Js::from(__('booking.admin.users.confirm_bulk_block')) }})">{{ __('booking.admin.users.bulk_block') }}</x-ui.button>
                        <x-ui.button type="submit" variant="secondary" :block="false" name="action" value="enabled" onclick="return confirm({{ Js::from(__('booking.admin.users.confirm_bulk_enable')) }})">{{ __('booking.admin.users.bulk_enable') }}</x-ui.button>
                    </form>
                    <x-ui.button variant="text" :block="false" class="ds-admin-bulk__clear" @click="selected = []">{{ __('booking.admin.users.bulk_clear') }}</x-ui.button>
                </div>

                <x-ui.table :caption="__('booking.admin.users.members')">
                    <x-slot:head>
                        <tr>
                            <th scope="col" class="ds-admin-check-cell">
                                <input type="checkbox" class="ds-admin-check" aria-label="{{ __('booking.admin.users.select_all') }}" @change="selected = $event.target.checked ? {{ json_encode($users->pluck('uid')->all()) }}.map(Number) : []" :checked="selected.length === {{ $users->count() }} && {{ $users->count() }} > 0">
                            </th>
                            <th scope="col">{{ __('booking.admin.users.name') }}</th>
                            <th scope="col">{{ __('booking.admin.users.email') }}</th>
                            <th scope="col">{{ __('booking.admin.users.role') }}</th>
                            <th scope="col">{{ __('booking.admin.users.status') }}</th>
                            <th scope="col">{{ __('booking.admin.users.last_booking') }}</th>
                            <th scope="col">{{ __('booking.admin.users.bookings_count') }}</th>
                            <th scope="col">{{ __('booking.admin.users.login_history_risk_score') }}</th>
                            <th scope="col" class="ds-admin-actions-cell">{{ __('booking.admin.users.actions') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach($users as $u)
                        @php($risk = $riskScores[$u->uid] ?? 0)
                        <tr :class="selected.includes({{ $u->uid }}) ? 'ds-row--own' : ''">
                            <td class="ds-admin-check-cell" data-label="{{ __('booking.admin.users.select_user', ['name' => $u->alias]) }}">
                                <input type="checkbox" class="ds-admin-check" aria-label="{{ __('booking.admin.users.select_user', ['name' => $u->alias]) }}" :value="{{ $u->uid }}" x-model.number="selected">
                            </td>
                            <td class="ds-cell--title" data-label="{{ __('booking.admin.users.name') }}">
                                <span class="ds-admin-person"><span class="ds-admin-initials" aria-hidden="true">{{ \App\Support\Ui\Initials::from($u->alias) }}</span><span>{{ $u->alias }}</span></span>
                            </td>
                            <td class="ds-admin-users__mail" data-label="{{ __('booking.admin.users.email') }}">{{ $u->email ?: __('booking.account.empty_option') }}</td>
                            <td data-label="{{ __('booking.admin.users.role') }}">{{ __('booking.admin.users.role_'.$u->effectiveRole()) }}</td>
                            <td class="ds-admin-users__status" data-label="{{ __('booking.admin.users.status') }}">
                                @php($accountStatus = $u->accountStatus())
                                <x-ui.badge :tone="\App\Support\Ui\UserStatusTone::for($accountStatus)->value">{{ in_array($accountStatus, \App\Models\User::STATUSES, true) ? __('booking.admin.users.status_'.$accountStatus) : $accountStatus }}</x-ui.badge>
                            </td>
                            <td data-label="{{ __('booking.admin.users.last_booking') }}">{{ $u->last_booking_date ? \Carbon\Carbon::parse($u->last_booking_date)->translatedFormat(__('booking.admin.users.last_booking_format')) : '–' }}</td>
                            <td data-label="{{ __('booking.admin.users.bookings_count') }}">{{ $u->bookings_count }}</td>
                            <td data-label="{{ __('booking.admin.users.login_history_risk_score') }}">
                                <x-ui.badge :tone="$risk > 0 ? 'attention' : 'neutral'">{{ $risk }}</x-ui.badge>
                            </td>
                            <td class="ds-admin-actions-cell" data-label="{{ __('booking.admin.users.actions') }}">
                                <x-ui.button :href="route('admin.users.edit', $u)" variant="secondary" :block="false">{{ __('booking.admin.common.edit') }}</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </x-ui.card>
        @endif
    @else
        <x-ui.card><p class="ds-admin-note">{{ __('booking.admin.search_hint') }}</p></x-ui.card>
    @endif
</div>
@endsection
