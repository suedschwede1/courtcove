@props([
    'square',
    'dateKey',
    'hour',
    'dateLabel',
    'isPast',
    'closed' => false,
    'reservation' => null,
    'isLoggedIn',
    'isAdmin',
    'authUserId',
    'date',
])

@php
    $h = $hour;
    $sid = $square->sid;
    $squareLabel = $square->display_name;
    $squareFullLabel = $square->name.' '.$square->display_name;
    $timeLabel = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
    $nextLabel = str_pad((string) ($h + 1), 2, '0', STR_PAD_LEFT) . ':00';
    $timeRangeLabel = $timeLabel . ' – ' . $nextLabel . ' ' . __('booking.admin.common.clock_suffix');

    $isOwn = $reservation
        && $isLoggedIn
        && isset($reservation->booking->user)
        && $reservation->booking->user->uid === $authUserId;

    $canManageBooking = $reservation && $isAdmin;
    $isSeriesBooking = $reservation?->booking?->isSubscription() ?? false;
    $isTournament = $reservation?->booking?->isChampionshipMatch() ?? false;
    $state = \App\Support\Calendar\CellState::for((bool) $reservation, $isPast, $isOwn, $isSeriesBooking, $isTournament)->value;

    // Detail line: the players without the owner (the owner is the name line already).
    $secondaryLabel = null;
    if ($isLoggedIn && $reservation?->booking) {
        $ownerKey = mb_strtolower(trim($reservation->booking->owner_label));
        $others = array_values(array_filter(
            $reservation->booking->player_names,
            fn (string $player) => mb_strtolower(trim($player)) !== $ownerKey,
        ));
        $secondaryLabel = $others !== [] ? implode(', ', $others) : null;
    }
    if ($secondaryLabel === null && $isSeriesBooking) {
        $secondaryLabel = __('booking.calendar.series_hint');
    }

    $cellClass = 'cc-over';
    $slotClass = $isPast ? ' slot-cell--past' : '';
    $seriesClass = $isSeriesBooking ? ' cc-series' : '';
    $primaryLabel = '';
    $action = null;
    $cellTitle = $squareLabel . ' – ' . __('booking.calendar.past');

    if ($isPast && !$reservation) {
        $cellClass = 'cc-over';
        $cellTitle = $squareLabel . ' – ' . __('booking.calendar.past');
    } elseif (!$reservation) {
        $cellClass = 'cc-free';
        $action = $isLoggedIn && !$isPast ? ($isAdmin ? 'admin-book' : 'book') : 'login';
        $cellTitle = $isLoggedIn
            ? __('booking.calendar.book_title', ['court' => $squareLabel, 'time' => $timeLabel])
            : __('booking.calendar.login_to_book');
    } elseif ($isOwn || ($canManageBooking && !$isPast)) {
        $cellClass = $isOwn ? 'cc-own' : 'cc-single-future';
        $action = 'cancel';
        $primaryLabel = $reservation->booking->owner_label;
        $cellTitle = $isOwn
            ? __('booking.calendar.cancel_title', ['court' => $squareLabel])
            : __('booking.calendar.edit_title', ['court' => $squareLabel]);
    } else {
        $cellClass = 'cc-single-future';
        $primaryLabel = $isLoggedIn
            ? ($reservation->booking?->owner_label ?? __('booking.calendar.occupied'))
            : __('booking.calendar.occupied');
        $cellTitle = $isPast
            ? ($squareLabel . ' – ' . __('booking.calendar.past'))
            : ($squareLabel . ' – ' . __('booking.calendar.occupied'));
    }

    if ($closed && ! $reservation) {
        $cellTitle = $squareLabel . ' – ' . __('booking.calendar.closed');
        $slotClass .= ' slot-cell--closed';
    }

    if ($isTournament) {
        $cellClass .= ' cc-turnier';
    }

    // Text lines of a booked slot: name, detail (players or the subscription hint)
    // and, on own bookings, the short label that replaces the detail on phones.
    $labels = array_filter([
        'cc-label-primary' => $primaryLabel,
        'cc-label-secondary' => $reservation ? $secondaryLabel : null,
        'cc-label-own' => $isOwn ? __('booking.calendar.own_short') : null,
    ], fn ($text) => $text !== null && $text !== '');

    $bookLabel = __('booking.calendar.book_cell', ['court' => $squareLabel, 'time' => $timeLabel]);
    $adminCreateUrl = route('admin.bookings.create') . '?sid=' . $sid . '&date=' . $dateKey . '&time_start=' . ($h * 3600) . '&time_end=' . (($h + 1) * 3600);
@endphp

@if($action === 'book')
    <a href="{{ route('bookings.create', ['sid' => $sid, 'date' => $dateKey, 'time_start' => $h]) }}"
       class="calendar-cell {{ $cellClass }}{{ $slotClass }}"
       data-state="{{ $state }}"
       aria-label="{{ $bookLabel }}"
       title="{{ $cellTitle }}"
       @click.prevent="$dispatch('open-booking', {
           sid: {{ $sid }},
           date: @js($dateKey),
           timeStart: {{ $h * 3600 }},
           timeEnd: {{ ($h + 1) * 3600 }},
           timeStartFormatted: @js(str_pad($h, 2, '0', STR_PAD_LEFT) . ':00'),
           timeEndFormatted: @js(str_pad($h + 1, 2, '0', STR_PAD_LEFT) . ':00'),
           squareName: @js($squareFullLabel),
           dateLabel: @js($dateLabel),
           timeLabel: @js($timeRangeLabel)
       })"></a>
@elseif($action === 'admin-book')
    <a href="{{ $adminCreateUrl }}"
       class="calendar-cell {{ $cellClass }}{{ $slotClass }} booking-trigger"
       data-state="{{ $state }}"
       aria-label="{{ $bookLabel }}"
       title="{{ $cellTitle }}"
       data-action="admin-book"
       data-sid="{{ $sid }}"
       data-date="{{ $dateKey }}"
       data-time-start="{{ $h * 3600 }}"
       data-time-end="{{ ($h + 1) * 3600 }}"
       data-square-name="{{ $squareFullLabel }}"
       data-date-label="{{ $dateLabel }}"
       data-time-label="{{ $timeRangeLabel }}"
       data-create-url="{{ $adminCreateUrl }}"></a>
@elseif($action === 'cancel' && $isAdmin)
    <a href="#"
       class="calendar-cell {{ $cellClass }}{{ $seriesClass }}{{ $slotClass }} booking-trigger"
       data-state="{{ $state }}"
       title="{{ $cellTitle }}"
       data-action="cancel"
       data-bid="{{ $reservation->booking->bid }}"
       data-square-name="{{ $squareFullLabel }}"
       data-date-label="{{ $dateLabel }}"
       data-time-label="{{ $timeRangeLabel }}"
       data-edit-url="{{ route('admin.bookings.edit', $reservation->booking) }}?occurrence={{ $dateKey }}"
       data-delete-url="{{ route('admin.bookings.destroy', $reservation->booking) }}">
        @foreach($labels as $labelClass => $labelText)
            <span class="{{ $labelClass }}">{{ $labelText }}</span>
        @endforeach
    </a>
@elseif($action === 'cancel' && $isOwn)
    <a href="#"
       class="calendar-cell {{ $cellClass }}{{ $seriesClass }}{{ $slotClass }}"
       data-state="{{ $state }}"
       title="{{ $cellTitle }}"
       @click.prevent="$dispatch('open-cancel', {
           bid: @js((string) $reservation->booking->bid),
           squareName: @js($squareFullLabel),
           dateLabel: @js($dateLabel),
           timeLabel: @js($timeRangeLabel),
           quantity: @js((string) $reservation->booking->quantity),
           mitspieler: @js($reservation->booking->player_names[0] ?? '')
       })">
        @foreach($labels as $labelClass => $labelText)
            <span class="{{ $labelClass }}">{{ $labelText }}</span>
        @endforeach
    </a>
@elseif($action === 'login')
    <a href="{{ route('login', ['redirect_to' => route('calendar.index', ['date' => $date->format('Y-m-d')])]) }}"
       class="calendar-cell {{ $cellClass }}{{ $slotClass }} guest-login-cell"
       data-state="{{ $state }}"
       aria-label="{{ __('booking.calendar.login_cell', ['court' => $squareLabel, 'time' => $timeLabel]) }}"
       title="{{ $cellTitle }}"></a>
@else
    <span class="calendar-cell {{ $cellClass }}{{ $seriesClass }}{{ $slotClass }}" data-state="{{ $state }}" title="{{ $cellTitle }}">
        @foreach($labels as $labelClass => $labelText)
            <span class="{{ $labelClass }}">{{ $labelText }}</span>
        @endforeach
    </span>
@endif
