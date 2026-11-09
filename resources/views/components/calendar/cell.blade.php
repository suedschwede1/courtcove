@props([
    'square',
    'dateKey',
    'hour',
    'dateLabel',
    'isPast',
    'closed' => false,
    'tooShort' => false,
    'slotStart' => null,
    'slotEnd' => null,
    'reservation' => null,
    'isLoggedIn',
    'isAdmin',
    'authUserId',
    'date',
    'playerExchangeOptedIn' => false,
])

@php
    $h = $hour;
    $sid = $square->sid;
    $squareLabel = $square->display_name;
    $squareFullLabel = $square->name.' '.$square->display_name;
    $slotStart ??= $h * 3600;
    $slotEnd ??= ($h + 1) * 3600;
    $timeLabel = $reservation ? substr((string) $reservation->time_start, 0, 5) : gmdate('H:i', $slotStart);
    $nextLabel = $reservation ? substr((string) $reservation->time_end, 0, 5) : gmdate('H:i', $slotEnd);
    $timeRangeLabel = $timeLabel . ' – ' . $nextLabel . ' ' . __('booking.admin.common.clock_suffix');

    $isOwn = $reservation
        && $isLoggedIn
        && isset($reservation->booking->user)
        && $reservation->booking->user->uid === $authUserId;

    $canManageBooking = $reservation && $isAdmin;
    $isSeriesBooking = $reservation?->booking?->isSubscription() ?? false;
    $isTournament = $reservation?->booking?->isChampionshipMatch() ?? false;

    // Player search: signups are eager-loaded by the controller, so remaining() and
    // the joined check read loaded data (no query per cell).
    $search = $reservation?->booking?->playerSearch;
    $hasOpenSearch = $search !== null && ! $isPast && ! $isTournament && $search->isSeeking();
    $searchRemaining = $hasOpenSearch ? $search->remaining() : 0;
    $isSeeking = $hasOpenSearch && ! $isOwn;
    // Signed up to a live search (open, filled or ended) before its deadline: the member may still
    // leave (PlayerExchangeService::leave), so his cell opens the join dialog also when nobody is
    // sought any more. The cell itself then looks like any other booking.
    $hasJoined = $search !== null && ! $isPast && ! $isTournament && $isLoggedIn && ! $isOwn
        && in_array($search->status, \App\Services\PlayerExchangeService::LIVE_STATUSES, true)
        && $search->ends_at->isFuture()
        && $search->signups->contains('uid', $authUserId);
    $joinedNotSeeking = $hasJoined && ! $hasOpenSearch;
    // Own booking: members signed up to its live search get a mail when it is cancelled (cancel dialog text).
    $ownLiveSignups = $isOwn && $search !== null && in_array($search->status, \App\Services\PlayerExchangeService::LIVE_STATUSES, true)
        ? $search->signups->count()
        : 0;

    $state = \App\Support\Calendar\CellState::for((bool) $reservation, $isPast, $isOwn, $isSeriesBooking, $isTournament, $isSeeking)->value;

    // Detail line: the players without the owner (the owner is the name line already).
    // An open search replaces it with the "still looking" line, so the cell keeps two lines.
    // The same list goes into the join dialog (joined members are written into the player names).
    $otherPlayers = [];
    if ($isLoggedIn && $reservation?->booking) {
        $otherPlayers = $reservation->booking->other_players;
    }
    $secondaryLabel = ! $hasOpenSearch && $otherPlayers !== [] ? implode(', ', $otherPlayers) : null;
    // Phones: the players (full names, two lines at most, "+N" after four; the title and the dialog list all).
    // Same visibility as the desktop detail line: members only, never during an open search.
    $playersShort = null;
    if ($secondaryLabel !== null && $reservation) {
        $playersShort = \App\Models\Booking::compactPlayerNames($otherPlayers);
    }
    if ($secondaryLabel === null && $isSeriesBooking) {
        $secondaryLabel = __('booking.calendar.series_hint');
    }

    $seekingShort = $hasOpenSearch ? trans_choice('booking.calendar.seeking_short', $searchRemaining) : null;
    $seekingLine = $hasOpenSearch ? trans_choice('booking.calendar.seeking_line', $searchRemaining) : null;
    $seekingTiny = $hasOpenSearch ? trans_choice('booking.calendar.seeking_tiny', $searchRemaining) : null;

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
        $cellClass = $isOwn ? 'cc-own' : ($isSeeking ? 'cc-seeking' : 'cc-single-future');
        $action = 'cancel';
        $primaryLabel = $reservation->booking->owner_label;
        $cellTitle = $isOwn
            ? __('booking.calendar.cancel_title', ['court' => $squareLabel])
            : __('booking.calendar.edit_title', ['court' => $squareLabel]);
        if ($hasOpenSearch) {
            $cellTitle .= ' – ' . $seekingLine;
        }
    } elseif ($isSeeking) {
        $cellClass = 'cc-seeking';
        $primaryLabel = $isLoggedIn
            ? ($reservation->booking?->owner_label ?? __('booking.calendar.occupied'))
            : __('booking.calendar.occupied');
        $action = ! $isLoggedIn ? 'seeking-login' : 'join';
        $cellTitle = $squareLabel . ' – ' . $seekingLine;
    } elseif ($joinedNotSeeking) {
        $cellClass = 'cc-single-future';
        $primaryLabel = $reservation->booking?->owner_label ?? __('booking.calendar.occupied');
        $action = 'join';
        $cellTitle = $squareLabel . ' – ' . __('booking.calendar.join.joined_title');
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

    if ($tooShort && ! $reservation) {
        $cellTitle = __('booking.messages.booking_duration_too_short');
    }

    if ($isTournament) {
        $cellClass .= ' cc-turnier';
    }

    // Text lines of a booked slot: name, detail (players or the subscription hint)
    // and, on own bookings, the short label that replaces the detail on phones.
    // An open search adds its text line (state never by colour alone) in up to three
    // lengths; CSS shows the one that fits the cell width (container query), so the
    // number is never cut off: the long line on own bookings (in place of "Mine"),
    // "still looking for N", and the shortest form for very narrow cells.
    $labels = array_filter([
        'cc-label-primary' => $primaryLabel,
        'cc-label-secondary' => $reservation ? $secondaryLabel : null,
        'cc-label-players' => $playersShort,
        'cc-label-own' => $isOwn && ! $hasOpenSearch ?__('booking.calendar.own_short') : null,
        'cc-label-seeking cc-label-seeking--long' => $hasOpenSearch && $isOwn ? $seekingLine : null,
        'cc-label-seeking cc-label-seeking--short' => $seekingShort,
        'cc-label-seeking cc-label-seeking--tiny' => $seekingTiny,
    ], fn ($text) => $text !== null && $text !== '');

    $bookLabel = __('booking.calendar.book_cell', ['court' => $squareLabel, 'time' => $timeLabel]);
    $seekingCellLabel = $hasOpenSearch
        ? __('booking.calendar.seeking_cell', ['court' => $squareLabel, 'time' => $timeLabel, 'remaining' => $seekingLine])
        : null;
    $adminCreateUrl = route('admin.bookings.create') . '?sid=' . $sid . '&date=' . $dateKey . '&time_start=' . $slotStart . '&time_end=' . $slotEnd;
    // Own booking with an open search: keep the usual name (owner, "Mine") and append the search
    // (the three visual lengths of the search line would otherwise all be read out).
    $ownSeekingLabel = $isOwn && $hasOpenSearch
        ? implode(' – ', array_filter([$primaryLabel, __('booking.calendar.own_short'), $seekingCellLabel]))
        : null;
    // Someone else's search, seen by a member: the booker's name first, like the visible name line
    // (guests see "Belegt" only, so their label stays without a name).
    $seekingNamedLabel = $isSeeking && $isLoggedIn
        ? implode(' – ', array_filter([$primaryLabel, $seekingCellLabel]))
        : $seekingCellLabel;
    if ($joinedNotSeeking) {
        $seekingNamedLabel = $primaryLabel . ' – ' . __('booking.calendar.joined_cell', ['court' => $squareLabel, 'time' => $timeLabel]);
    }
    // Someone else's plain booking, seen by a member: tapping opens the list of all names (phones cut the cell text).
    $showPlayers = $action === null && $reservation && $isLoggedIn && $otherPlayers !== [] && ! $closed;
    $playersTitle = $showPlayers ? $reservation->booking->owner_label.', '.implode(', ', $otherPlayers) : null;
    $dayUrl = route('calendar.index', ['date' => $date->format('Y-m-d')]);
    $loginUrl = route('login', ['redirect_to' => $dayUrl]);
@endphp

@if($action === 'book')
    <a href="{{ route('bookings.create', ['sid' => $sid, 'date' => $dateKey, 'time_start' => $h] + (($slotStart !== $h * 3600 || $slotEnd !== ($h + 1) * 3600) ? ['slot_start' => $slotStart, 'slot_end' => $slotEnd] : [])) }}"
       class="calendar-cell {{ $cellClass }}{{ $slotClass }}"
       data-state="{{ $state }}"
       aria-label="{{ $bookLabel }}"
       title="{{ $cellTitle }}"
       @click.prevent="$dispatch('open-booking', {
           sid: {{ $sid }},
           date: @js($dateKey),
           timeStart: {{ $slotStart }},
           timeEnd: {{ $slotEnd }},
           timeStartFormatted: @js($timeLabel),
           timeEndFormatted: @js($nextLabel),
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
       data-time-start="{{ $slotStart }}"
       data-time-end="{{ $slotEnd }}"
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
       @if($ownSeekingLabel) aria-label="{{ $ownSeekingLabel }}" @endif
       title="{{ $cellTitle }}"
       @click.prevent="$dispatch('open-cancel', {
           bid: @js((string) $reservation->booking->bid),
           squareName: @js($squareFullLabel),
           dateLabel: @js($dateLabel),
           timeLabel: @js($timeRangeLabel),
           quantity: @js((string) $reservation->booking->quantity),
           mitspieler: @js($reservation->booking->player_names[0] ?? ''),
           searchOpen: @js($hasOpenSearch),
           searchRemaining: {{ $searchRemaining }},
           searchSignups: {{ $ownLiveSignups }},
           players: @js($otherPlayers)
       })">
        @foreach($labels as $labelClass => $labelText)
            <span class="{{ $labelClass }}">{{ $labelText }}</span>
        @endforeach
    </a>
@elseif($action === 'join')
    <a href="{{ $dayUrl }}"
       class="calendar-cell {{ $cellClass }}{{ $slotClass }}"
       data-state="{{ $state }}"
       aria-label="{{ $seekingNamedLabel }}"
       title="{{ $cellTitle }}"
       @click.prevent="$dispatch('open-join', {
           bid: @js((string) $reservation->booking->bid),
           squareName: @js($squareFullLabel),
           dateLabel: @js($dateLabel),
           timeLabel: @js($timeRangeLabel),
           owner: @js($primaryLabel),
           remaining: {{ $searchRemaining }},
           joined: @js($hasJoined),
           optedIn: @js((bool) $playerExchangeOptedIn),
           players: @js($otherPlayers)
       })">
        @foreach($labels as $labelClass => $labelText)
            <span class="{{ $labelClass }}">{{ $labelText }}</span>
        @endforeach
    </a>
@elseif($action === 'seeking-login')
    <a href="{{ $loginUrl }}"
       class="calendar-cell {{ $cellClass }}{{ $slotClass }} guest-login-cell"
       data-state="{{ $state }}"
       aria-label="{{ $seekingCellLabel }}"
       title="{{ $cellTitle }}">
        @foreach($labels as $labelClass => $labelText)
            <span class="{{ $labelClass }}">{{ $labelText }}</span>
        @endforeach
    </a>
@elseif($action === 'login')
    <a href="{{ $loginUrl }}"
       class="calendar-cell {{ $cellClass }}{{ $slotClass }} guest-login-cell"
       data-state="{{ $state }}"
       aria-label="{{ __('booking.calendar.login_cell', ['court' => $squareLabel, 'time' => $timeLabel]) }}"
       title="{{ $cellTitle }}"></a>
@elseif($showPlayers)
    <a href="#"
       class="calendar-cell {{ $cellClass }}{{ $seriesClass }}{{ $slotClass }}"
       data-state="{{ $state }}"
       aria-label="{{ $playersTitle }}"
       title="{{ $playersTitle }}"
       @click.prevent="$dispatch('open-players', {
           squareName: @js($squareFullLabel),
           dateLabel: @js($dateLabel),
           timeLabel: @js($timeRangeLabel),
           owner: @js($reservation->booking->owner_label),
           players: @js($otherPlayers)
       })">
        @foreach($labels as $labelClass => $labelText)
            <span class="{{ $labelClass }}">{{ $labelText }}</span>
        @endforeach
    </a>
@else
    <span class="calendar-cell {{ $cellClass }}{{ $seriesClass }}{{ $slotClass }}" data-state="{{ $state }}" title="{{ $cellTitle }}">
        @foreach($labels as $labelClass => $labelText)
            <span class="{{ $labelClass }}">{{ $labelText }}</span>
        @endforeach
    </span>
@endif
