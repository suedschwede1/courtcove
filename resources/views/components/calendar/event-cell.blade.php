@props([
    'name',
    'event',
    'part' => 'single',
    'canAdminEvents' => false,
    'dateKey' => null,
])

@php
    $leads = in_array($part, ['single', 'start'], true);
    $editUrl = $leads && $canAdminEvents ? route('admin.events.edit', $event) : null;

    // The time range only shows when the whole event lies on this card's date.
    $range = null;
    $start = $event->datetime_start ?? null;
    $end = $event->datetime_end ?? null;
    if ($leads && $start && $end && $start->format('Y-m-d') === $dateKey && $end->format('Y-m-d') === $dateKey) {
        $clock = fn ($time) => $time->format($time->minute === 0 ? 'G' : 'G:i');
        $range = __('booking.calendar.event_range', ['from' => $clock($start), 'to' => $clock($end)]);
    }
@endphp

<span @class(['calendar-cell', 'event-cell', 'event-cell--'.$part, 'event-cell--editable' => $canAdminEvents]) data-state="{{ \App\Support\Calendar\CellState::Event->value }}" title="{{ $name }}">
    @if($editUrl)
        <a href="{{ $editUrl }}" class="event-label booking-trigger" data-action="event-edit" data-edit-url="{{ $editUrl }}">{{ $name }}</a>
    @elseif($leads)
        <span class="event-label">{{ $name }}</span>
    @else
        <span class="event-label" aria-hidden="true">{{ $name }}</span>
    @endif
    @if($range)
        <span class="event-range">{{ $range }}</span>
    @endif
</span>
