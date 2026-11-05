@props([
    'name',
    'event',
    'part' => 'single',
    'canAdminEvents' => false,
])

@php
    $leads = in_array($part, ['single', 'start'], true);
    $editUrl = $leads && $canAdminEvents ? route('admin.events.edit', $event) : null;
@endphp

<span @class(['calendar-cell', 'event-cell', 'event-cell--'.$part, 'event-cell--editable' => $canAdminEvents]) data-state="{{ \App\Support\Calendar\CellState::Event->value }}" title="{{ $name }}">
    @if($editUrl)
        <a href="{{ $editUrl }}" class="event-label booking-trigger" data-action="event-edit" data-edit-url="{{ $editUrl }}">{{ $name }}</a>
    @elseif($leads)
        <span class="event-label">{{ $name }}</span>
    @else
        <span class="event-label" aria-hidden="true">{{ $name }}</span>
    @endif
</span>
