@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.title'))
@section('admin-content')
    <h1>{{ __('booking.admin.users.title') }}</h1>
    <hr class="admin-separator">

    <form method="GET" action="{{ route('admin.users.index') }}" class="admin-filter-bar">
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               placeholder="{{ __('booking.admin.search_placeholder') }}" class="admin-filter-bar__input">
        <select name="status" class="admin-filter-bar__select">
            <option value="">{{ __('booking.admin.search_status_all') }}</option>
            <option value="admin"    @selected(($filters['status'] ?? '') === 'admin')>admin</option>
            <option value="assist"   @selected(($filters['status'] ?? '') === 'assist')>assist</option>
            <option value="enabled"  @selected(($filters['status'] ?? '') === 'enabled')>enabled</option>
            <option value="disabled" @selected(($filters['status'] ?? '') === 'disabled')>disabled</option>
        </select>
        <button type="submit" class="admin-btn-primary">{{ __('booking.admin.common.filter') }}</button>
        <a href="{{ route('admin.users.create') }}" class="default-button">{{ __('booking.admin.users.new_user') }}</a>
    </form>

    @if($searched)
        @if($users->isEmpty())
            <p class="admin-no-results">{{ __('booking.admin.no_results') }}</p>
        @else
            <table class="admin-table">
                <thead><tr>
                    <th>{{ __('booking.admin.users.name') }}</th>
                    <th>{{ __('booking.admin.users.email') }}</th>
                    <th>{{ __('booking.admin.users.status') }}</th>
                    <th></th>
                </tr></thead>
                <tbody>
                @foreach($users as $u)
                    <tr>
                        <td>{{ $u->alias }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $u->status }}</td>
                        <td class="admin-table__actions">
                            <a href="{{ route('admin.users.edit', $u) }}">{{ __('booking.admin.common.edit') }}</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @else
        <p class="admin-no-results">{{ __('booking.admin.search_hint') }}</p>
    @endif
@endsection
