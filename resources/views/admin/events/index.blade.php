@extends('layouts.admin')
@section('admin-title', __('booking.admin.events.title'))
@section('admin-content')
    <h1>{{ __('booking.admin.events.title') }}</h1>
    <hr class="admin-separator">
    <a href="{{ route('admin.events.create') }}" class="default-button">{{ __('booking.admin.events.new_event') }}</a>
    <table class="admin-table">
        <thead><tr><th>{{ __('booking.admin.events.name') }}</th><th>{{ __('booking.admin.events.court') }}</th><th>{{ __('booking.admin.events.from') }}</th><th>{{ __('booking.admin.events.to') }}</th><th>{{ __('booking.admin.events.status') }}</th><th></th></tr></thead>
        <tbody>
        @foreach($events as $event)
            <tr>
                <td>{{ $event->meta->firstWhere('key', 'name')?->value ?? '—' }}</td>
                <td>{{ $event->square?->display_name ?? __('booking.admin.events.all_courts') }}</td>
                <td>{{ $event->datetime_start?->format('d.m.Y H:i') }}</td>
                <td>{{ $event->datetime_end?->format('d.m.Y H:i') }}</td>
                <td>{{ $event->status }}</td>
                <td class="admin-table__actions">
                    <a href="{{ route('admin.events.edit', $event) }}">{{ __('booking.admin.common.edit') }}</a>
                    <form method="POST" action="{{ route('admin.events.destroy', $event) }}" onsubmit="return confirm('{{ __('booking.admin.events.confirm_delete') }}')">
                        @method('DELETE') @csrf
                        <button type="submit" class="default-button">{{ __('booking.admin.common.delete') }}</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
