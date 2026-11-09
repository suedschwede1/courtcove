@if(session('event_conflicts'))
    <x-ui.notice tone="attention">
        <p>{{ __('booking.validation.event_booking_conflicts', ['count' => count(session('event_conflicts'))]) }}</p>
        <ul>
            @foreach(session('event_conflicts') as $conflict)
                <li>
                    @if(auth()->user()?->can('admin.booking'))
                        <a href="{{ route('admin.bookings.edit', ['booking' => $conflict['bid'], 'occurrence' => $conflict['date']]) }}">#{{ $conflict['bid'] }} · {{ $conflict['date'] }} · {{ substr($conflict['time_start'], 0, 5) }}–{{ substr($conflict['time_end'], 0, 5) }}</a>
                    @else
                        #{{ $conflict['bid'] }} · {{ $conflict['date'] }} · {{ substr($conflict['time_start'], 0, 5) }}–{{ substr($conflict['time_end'], 0, 5) }}
                    @endif
                </li>
            @endforeach
        </ul>
    </x-ui.notice>
@endif
