@extends('layouts.admin')
@section('admin-title', __('booking.admin.squares.title'))
@section('admin-content')
    <h1>{{ __('booking.admin.squares.title') }}</h1>
    <a href="{{ route('admin.squares.create') }}" class="default-button">{{ __('booking.admin.squares.new_square') }}</a>
    <table class="booking-grid">
        <thead><tr><th>{{ __('booking.admin.squares.name') }}</th><th>{{ __('booking.admin.squares.display_name') }}</th><th>{{ __('booking.admin.squares.status') }}</th><th>{{ __('booking.admin.squares.time_column') }}</th><th>{{ __('booking.admin.squares.time_block_column') }}</th><th></th></tr></thead>
        <tbody>
        @foreach($squares as $square)
            <tr>
                <td>{{ $square->name }}</td>
                <td>{{ $square->display_name }}</td>
                <td>{{ $square->status->value }}</td>
                <td>{{ substr((string) $square->time_start, 0, 5) }}–{{ substr((string) $square->time_end, 0, 5) }} {{ __('booking.admin.common.clock_suffix') }}</td>
                <td>{{ (int) round($square->time_block / 60) }} {{ __('booking.admin.common.minutes_suffix') }}</td>
                <td>
                    <a href="{{ route('admin.squares.edit', $square) }}">{{ __('booking.admin.common.edit') }}</a>
                    <form method="POST" action="{{ route('admin.squares.destroy', $square) }}" onsubmit="return confirm('{{ __('booking.admin.squares.confirm_delete') }}')" style="display:inline">
                        @method('DELETE') @csrf
                        <button type="submit" class="abmelden-button default-button">{{ __('booking.admin.common.delete') }}</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
