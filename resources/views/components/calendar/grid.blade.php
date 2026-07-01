@props([
    'dates',
    'squares',
    'dateLabels',
    'reservationsBySlot',
    'eventBlocks',
    'eventSkip',
    'today',
    'now',
    'isLoggedIn',
    'isAdmin',
    'authUserId',
    'canAdminEvents',
    'date',
])

<table class="calendar-square-table booking-grid" id="calendar-grid" data-squares="{{ $squares->count() }}" data-max-days="{{ count($dates) }}">
    <colgroup>
        <col class="calendar-time-col">
        @foreach($dates as $dayIndex => $d)
            @foreach($squares as $square)
                <col @class(['calendar-slot-col', 'cal-extra-day' => $dayIndex >= 3]) data-day="{{ $dayIndex }}">
            @endforeach
        @endforeach
    </colgroup>
    <thead>
        <tr class="calendar-date-row">
            <td class="time-spacer">&nbsp;</td>
            @foreach($dates as $dayIndex => $d)
                @php
                    $isSelectedDay = $d->format('Y-m-d') === $date->format('Y-m-d');
                    $isTodayColumn = $d->format('Y-m-d') === $today;
                @endphp
                <td colspan="{{ $squares->count() }}" @class(['day-header-cell', 'day-header-cell--active' => $isSelectedDay, 'day-header-cell--today' => $isTodayColumn, 'cal-extra-day' => $dayIndex >= 3]) data-day="{{ $dayIndex }}">
                    <div class="day-header-inline">
                        <span class="day-header-name">{{ $dateLabels[$d->format('Y-m-d')]['short'] }}</span>
                        <span class="day-header-date">{{ $dateLabels[$d->format('Y-m-d')]['long'] }}</span>
                    </div>
                </td>
            @endforeach
        </tr>
        <tr class="calendar-square-row">
            <td class="time-side-head">{{ __('booking.calendar.court') }}</td>
            @foreach($dates as $dayIndex => $d)
                @php
                    $isSelectedDay = $d->format('Y-m-d') === $date->format('Y-m-d');
                    $isTodayColumn = $d->format('Y-m-d') === $today;
                @endphp
                @foreach($squares as $square)
                    <td @class(['square-head-cell', 'square-head-cell--active' => $isSelectedDay, 'square-head-cell--today' => $isTodayColumn, 'cal-extra-day' => $dayIndex >= 3]) data-day="{{ $dayIndex }}">
                        <span class="square-head-title">{{ $square->name }}</span>
                        @if($square->display_name !== $square->name)
                            <span class="square-head-alias">{{ $square->display_name }}</span>
                        @endif
                    </td>
                @endforeach
            @endforeach
        </tr>
    </thead>
    <tbody>
        @for($h = 8; $h <= 19; $h++)
            @php
                $timeLabel = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
                $nextLabel = str_pad((string) ($h + 1), 2, '0', STR_PAD_LEFT) . ':00';
                $timeRangeLabel = $timeLabel . ' – ' . $nextLabel . ' ' . __('booking.admin.common.clock_suffix');
            @endphp
            <tr class="calendar-core-row">
                <td class="time-cell">
                    <span class="time-main">{{ $timeLabel }}</span>
                    <span class="time-sub">{{ __('booking.calendar.to_time', ['time' => $nextLabel]) }}</span>
                </td>

                @foreach($dates as $dayIndex => $d)
                    @php
                        $dateKey = $d->format('Y-m-d');
                        $isPastSlot = $dateKey < $today || ($dateKey === $today && ($h + 1) <= $now->hour);
                        $extraDay = $dayIndex >= 3;
                    @endphp
                    @foreach($squares as $square)
                        @php
                            $sid = $square->sid;
                            $squareLabel = $square->display_name;
                            $skipSlot = !empty($eventSkip[$dateKey][$sid][$h]);
                        @endphp

                        @continue($skipSlot)

                        @php
                            $evBlock = $eventBlocks[$dateKey][$sid][$h] ?? null;
                            $reservation = $reservationsBySlot[$dateKey][$sid][$h] ?? null;

                            $isOwn = $reservation
                                && $isLoggedIn
                                && isset($reservation->booking->user)
                                && $reservation->booking->user->uid === $authUserId;

                            $canManageBooking = $reservation && $isAdmin;
                            $isSeriesBooking = $reservation?->booking?->isSubscription() ?? false;

                            $secondaryLabel = $isLoggedIn ? $reservation?->booking?->player_names_label : null;

                            $cellClass = 'cc-over';
                            $slotClass = $isPastSlot ? ' slot-cell--past' : '';
                            $seriesClass = $isAdmin && $isSeriesBooking ? ' cc-series' : '';
                            $primaryLabel = '';
                            $action = null;
                            $cellTitle = $squareLabel . ' – ' . __('booking.calendar.past');

                            if (!$evBlock) {
                                if ($isPastSlot && !$reservation) {
                                    $cellClass = 'cc-over';
                                    $cellTitle = $squareLabel . ' – ' . __('booking.calendar.past');
                                } elseif (!$reservation) {
                                    $cellClass = 'cc-free';
                                    $action = $isLoggedIn && !$isPastSlot ? ($isAdmin ? 'admin-book' : 'book') : 'login';
                                    $cellTitle = $isLoggedIn
                                        ? __('booking.calendar.book_title', ['court' => $squareLabel, 'time' => $timeLabel])
                                        : __('booking.calendar.login_to_book');
                                } elseif ($isOwn || ($canManageBooking && !$isPastSlot)) {
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
                                    $cellTitle = $isPastSlot
                                        ? ($squareLabel . ' – ' . __('booking.calendar.past'))
                                        : ($squareLabel . ' – ' . __('booking.calendar.occupied'));
                                }
                            }
                        @endphp

                        @if($evBlock)
                            @php
                                $eventEditUrl = $canAdminEvents ? route('admin.events.edit', $evBlock['event']) : null;
                            @endphp
                            <td rowspan="{{ $evBlock['rows'] }}" colspan="{{ $evBlock['cols'] }}" @class(['event-cell', 'event-cell--editable' => $canAdminEvents, 'cal-extra-day' => $extraDay]) data-day="{{ $dayIndex }}" title="{{ $evBlock['name'] }}">
                                @if($eventEditUrl)
                                    <a href="{{ $eventEditUrl }}" class="event-label booking-trigger" data-action="event-edit" data-edit-url="{{ $eventEditUrl }}">{{ $evBlock['name'] }}</a>
                                @else
                                    <span class="event-label">{{ $evBlock['name'] }}</span>
                                @endif
                            </td>
                        @elseif($action === 'book' || $action === 'admin-book')
                            <td @class(['cal-extra-day' => $extraDay]) data-day="{{ $dayIndex }}">
                                @if($action === 'book')
                                    <a href="#"
                                       class="calendar-cell {{ $cellClass }}{{ $slotClass }}"
                                       title="{{ $cellTitle }}"
                                       @click.prevent="$dispatch('open-booking', {
                                           sid: {{ $sid }},
                                           date: @js($dateKey),
                                           timeStart: {{ $h * 3600 }},
                                           timeEnd: {{ ($h + 1) * 3600 }},
                                           timeStartFormatted: @js(str_pad($h, 2, '0', STR_PAD_LEFT) . ':00'),
                                           timeEndFormatted: @js(str_pad($h + 1, 2, '0', STR_PAD_LEFT) . ':00'),
                                           squareName: @js($squareLabel),
                                           dateLabel: @js($dateLabels[$d->format('Y-m-d')]['full']),
                                           timeLabel: @js($timeRangeLabel)
                                       })"></a>
                                @else
                                    <a href="#"
                                       class="calendar-cell {{ $cellClass }}{{ $slotClass }} booking-trigger"
                                       title="{{ $cellTitle }}"
                                       data-action="admin-book"
                                       data-sid="{{ $sid }}"
                                       data-date="{{ $dateKey }}"
                                       data-time-start="{{ $h * 3600 }}"
                                       data-time-end="{{ ($h + 1) * 3600 }}"
                                       data-square-name="{{ $squareLabel }}"
                                       data-date-label="{{ $dateLabels[$d->format('Y-m-d')]['full'] }}"
                                       data-time-label="{{ $timeRangeLabel }}"
                                       data-create-url="{{ route('admin.bookings.create') }}?sid={{ $sid }}&date={{ $dateKey }}&time_start={{ $h * 3600 }}&time_end={{ ($h + 1) * 3600 }}"></a>
                                @endif
                            </td>
                        @elseif($action === 'cancel')
                            <td @class(['cal-extra-day' => $extraDay]) data-day="{{ $dayIndex }}">
                                @if($isAdmin)
                                    <a href="#"
                                       class="calendar-cell {{ $cellClass }}{{ $slotClass }}{{ $seriesClass }} booking-trigger"
                                       title="{{ $cellTitle }}"
                                       data-action="cancel"
                                       data-bid="{{ $reservation->booking->bid }}"
                                       data-square-name="{{ $squareLabel }}"
                                       data-date-label="{{ $dateLabels[$d->format('Y-m-d')]['full'] }}"
                                       data-time-label="{{ $timeRangeLabel }}"
                                       data-edit-url="{{ route('admin.bookings.edit', $reservation->booking) }}"
                                       data-delete-url="{{ route('admin.bookings.destroy', $reservation->booking) }}">
                                        <span class="cc-label-primary">{{ $primaryLabel }}</span>
                                        @if($secondaryLabel)
                                            <span class="cc-label-secondary">{{ $secondaryLabel }}</span>
                                        @endif
                                    </a>
                                @elseif($isOwn)
                                    <a href="#"
                                       class="calendar-cell {{ $cellClass }}{{ $slotClass }}{{ $seriesClass }}"
                                       title="{{ $cellTitle }}"
                                       @click.prevent="$dispatch('open-cancel', {
                                           bid: @js((string) $reservation->booking->bid),
                                           squareName: @js($squareLabel),
                                           dateLabel: @js($dateLabels[$d->format('Y-m-d')]['full']),
                                           timeLabel: @js($timeRangeLabel),
                                           quantity: @js((string) $reservation->booking->quantity),
                                           mitspieler: @js($reservation->booking->player_names[0] ?? '')
                                       })">
                                        <span class="cc-label-primary">{{ $primaryLabel }}</span>
                                        @if($secondaryLabel)
                                            <span class="cc-label-secondary">{{ $secondaryLabel }}</span>
                                        @endif
                                    </a>
                                @else
                                    <span class="calendar-cell {{ $cellClass }}{{ $slotClass }}{{ $seriesClass }}" title="{{ $cellTitle }}">
                                        <span class="cc-label-primary">{{ $primaryLabel }}</span>
                                        @if($secondaryLabel)
                                            <span class="cc-label-secondary">{{ $secondaryLabel }}</span>
                                        @endif
                                    </span>
                                @endif
                            </td>
                        @elseif($action === 'login')
                            <td @class(['cal-extra-day' => $extraDay]) data-day="{{ $dayIndex }}">
                                <a href="{{ route('login', ['redirect_to' => route('calendar.index', ['date' => $date->format('Y-m-d')])]) }}"
                                   class="calendar-cell {{ $cellClass }}{{ $slotClass }} guest-login-cell"
                                   title="{{ $cellTitle }}"></a>
                            </td>
                        @else
                            <td @class(['cal-extra-day' => $extraDay]) data-day="{{ $dayIndex }}">
                                <span class="calendar-cell {{ $cellClass }}{{ $slotClass }}{{ $seriesClass }}" title="{{ $cellTitle }}">
                                    @if($primaryLabel)
                                        <span class="cc-label-primary">{{ $primaryLabel }}</span>
                                    @endif
                                    @if($secondaryLabel)
                                        <span class="cc-label-secondary">{{ $secondaryLabel }}</span>
                                    @endif
                                </span>
                            </td>
                        @endif
                    @endforeach
                @endforeach
            </tr>
        @endfor
    </tbody>
    <tfoot>
        <tr class="calendar-square-row">
            <td class="time-side-head">{{ __('booking.calendar.court') }}</td>
            @foreach($dates as $dayIndex => $d)
                @php
                    $isSelectedDay = $d->format('Y-m-d') === $date->format('Y-m-d');
                    $isTodayColumn = $d->format('Y-m-d') === $today;
                @endphp
                @foreach($squares as $square)
                    <td @class(['square-head-cell', 'square-head-cell--active' => $isSelectedDay, 'square-head-cell--today' => $isTodayColumn, 'cal-extra-day' => $dayIndex >= 3]) data-day="{{ $dayIndex }}">
                        <span class="square-head-title">{{ $square->name }}</span>
                        @if($square->display_name !== $square->name)
                            <span class="square-head-alias">{{ $square->display_name }}</span>
                        @endif
                    </td>
                @endforeach
            @endforeach
        </tr>
        <tr class="calendar-date-row footer-date-row">
            <td class="time-spacer">&nbsp;</td>
            @foreach($dates as $dayIndex => $d)
                @php
                    $isSelectedDay = $d->format('Y-m-d') === $date->format('Y-m-d');
                    $isTodayColumn = $d->format('Y-m-d') === $today;
                @endphp
                <td colspan="{{ $squares->count() }}" @class(['day-header-cell', 'day-header-cell--active' => $isSelectedDay, 'day-header-cell--today' => $isTodayColumn, 'cal-extra-day' => $dayIndex >= 3]) data-day="{{ $dayIndex }}">
                    <div class="day-header-inline"><span class="day-header-name">{{ $dateLabels[$d->format('Y-m-d')]['short'] }}</span><span class="day-header-date">{{ $dateLabels[$d->format('Y-m-d')]['long'] }}</span></div>
                </td>
            @endforeach
        </tr>
    </tfoot>
</table>

@push('scripts')
<script>
(function () {
    var grid = document.getElementById('calendar-grid');
    if (!grid) { return; }

    var wrap = grid.closest('.calendar-wrap') || grid.parentElement;
    var squares = parseInt(grid.dataset.squares, 10) || 1;
    var maxDays = parseInt(grid.dataset.maxDays, 10) || 3;
    var dayCells = grid.querySelectorAll('[data-day]');
    var activeDayCell = grid.querySelector('.day-header-cell--active[data-day]');
    var selectedDay = activeDayCell ? (parseInt(activeDayCell.getAttribute('data-day'), 10) || 0) : 0;

    function baseDays() {
        if (window.matchMedia('(max-width: 900px)').matches) {
            return 1;
        }

        return 3;
    }

    function cssPx(name, fallback) {
        var value = parseFloat(getComputedStyle(grid).getPropertyValue(name));
        return isNaN(value) ? fallback : value;
    }

    function visibleDayCount() {
        var minimumDays = baseDays();
        var dayWidth = squares * cssPx('--calendar-slot-col', 136);
        if (dayWidth <= 0) { return minimumDays; }

        var fits = Math.floor((wrap.clientWidth - cssPx('--calendar-time-col', 94)) / dayWidth);
        return Math.max(minimumDays, Math.min(maxDays, fits));
    }

    function applyVisibleDays() {
        var count = visibleDayCount();
        var timeWidth = cssPx('--calendar-time-col', 94);
        var dayWidth = squares * cssPx('--calendar-slot-col', 136);
        var mobileOnlyOneDay = window.matchMedia('(max-width: 900px)').matches;
        var firstVisibleDay = mobileOnlyOneDay ? selectedDay : 0;
        var lastVisibleDay = firstVisibleDay + count;

        dayCells.forEach(function (el) {
            var dayIndex = parseInt(el.getAttribute('data-day'), 10);
            el.classList.toggle('is-visible', dayIndex >= firstVisibleDay && dayIndex < lastVisibleDay);
        });

        grid.dataset.visibleDays = String(count);
        wrap.dataset.visibleDays = String(count);

        if (mobileOnlyOneDay) {
            grid.style.minWidth = (timeWidth + (count * dayWidth)) + 'px';
        } else {
            grid.style.minWidth = '';
        }
    }

    var timer;
    window.addEventListener('resize', function () {
        clearTimeout(timer);
        timer = setTimeout(applyVisibleDays, 100);
    });

    applyVisibleDays();
})();
</script>
@endpush

