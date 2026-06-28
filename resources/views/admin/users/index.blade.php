@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.title'))
@section('admin-content')
    <h1>{{ __('booking.admin.users.title') }}</h1>
    <hr class="admin-separator">
    <a href="{{ route('admin.users.create') }}" class="default-button">{{ __('booking.admin.users.new_user') }}</a>
    <table class="admin-table">
        <thead><tr><th>{{ __('booking.admin.users.name') }}</th><th>{{ __('booking.admin.users.email') }}</th><th>{{ __('booking.admin.users.status') }}</th><th></th></tr></thead>
        <tbody>
        @foreach($users as $u)
            <tr>
                <td>{{ $u->alias }}</td>
                <td>{{ $u->email }}</td>
                <td>{{ $u->status }}</td>
                <td class="admin-table__actions"><a href="{{ route('admin.users.edit', $u) }}">{{ __('booking.admin.common.edit') }}</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
