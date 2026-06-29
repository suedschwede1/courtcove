@extends('layouts.app')
@section('title', __('booking.calendar.title', ['date' => $date->format('d.m.Y')]))

@push('header-nav')
<a href="{{ route('calendar.index', ['date' => $date->copy()->subDay()->format('Y-m-d')]) }}"
   class="default-button nav-arrow"
   title="{{ __('booking.nav.previous_day') }}">&#9664;</a>

<a href="{{ route('calendar.index') }}" class="default-button">{{ __('booking.nav.today') }}</a>

<form method="GET" action="{{ route('calendar.index') }}" class="date-switcher-form">
    <div class="date-picker-wrap">
        <span class="date-picker-label" id="c-date-label">{{ $date->format('d.m.Y') }}</span>
        <input type="date" name="date" id="c-date"
               value="{{ $date->format('Y-m-d') }}"
               class="date-switcher-input date-switcher-input--native date-switcher-input--overlay"
               aria-label="{{ __('booking.nav.choose_date') }}">
    </div>
</form>

<a href="{{ route('calendar.index', ['date' => $date->copy()->addDay()->format('Y-m-d')]) }}"
   class="default-button nav-arrow"
   title="{{ __('booking.nav.next_day') }}">&#9654;</a>
@endpush

@section('calendar-system-info')
<div class="help-panel__grid help-panel__grid--single">
    <section class="help-card">
        <p class="help-card__eyebrow">{{ __('booking.calendar.system_eyebrow') }}</p>
        <h2 class="help-card__title">{{ __('booking.calendar.information') }}</h2>
                <p class="help-card__text">
            {{ __('booking.calendar.system_text') }}
        </p>
        <ul class="help-card__list">
            @foreach(__('booking.calendar.system_items') as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </section>
</div>
@endsection

@section('calendar-help')
<div class="help-panel__grid">
    @auth
        <section class="help-card">
            <p class="help-card__eyebrow">{{ __('booking.calendar.my_area') }}</p>
            <h2 class="help-card__title">{{ $authUser->name }}</h2>
                        <p class="help-card__text">
                {{ __('booking.calendar.member_text') }}
            </p>
            <div class="help-card__status">
                <span class="help-card__status-label">{{ __('booking.calendar.status') }}</span>
                <strong>{{ __('booking.calendar.member_logged_in') }}</strong>
            </div>
        </section>
    @endauth

    <section class="help-card">
        <p class="help-card__eyebrow">{{ __('booking.nav.help') }}</p>
        <h2 class="help-card__title">{{ __('booking.calendar.help_heading') }}</h2>
                <ul class="help-card__list">
            @foreach(__('booking.calendar.help_items') as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </section>
</div>
@endsection

@section('content')
<div class="calendar-layout">
    <div class="calendar-wrap">
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
                        <td colspan="{{ $squares->count() }}" @class(['day-header-cell', 'cal-extra-day' => $dayIndex >= 3]) data-day="{{ $dayIndex }}">
                            <div class="day-header-inline"><span class="day-header-name">{{ $dateLabels[$d->format('Y-m-d')]['short'] }}</span><span class="day-header-date">{{ $dateLabels[$d->format('Y-m-d')]['long'] }}</span></div>
                        </td>
                    @endforeach
                </tr>
                <tr class="calendar-square-row">
                    <td class="time-side-head">{{ __('booking.calendar.court') }}</td>
                    @foreach($dates as $dayIndex => $d)
                        @foreach($squares as $square)
                            @php
                                $squareAlias = $square->display_name !== $square->name ? $square->display_name : null;
                            @endphp
                            <td @class(['square-head-cell', 'cal-extra-day' => $dayIndex >= 3]) data-day="{{ $dayIndex }}">
                                <span class="square-head-title">{{ $square->name }}</span>
                                @if($squareAlias)
                                    <span class="square-head-alias">{{ $squareAlias }}</span>
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

                                    if (!empty($eventSkip[$dateKey][$sid][$h])) { continue; }

                                    $evBlock = $eventBlocks[$dateKey][$sid][$h] ?? null;
                                    $reservation = $reservationsBySlot[$dateKey][$sid][$h] ?? null;

                                    $isOwn = $reservation
                                        && $isLoggedIn
                                        && isset($reservation->booking->user)
                                        && $reservation->booking->user->uid === $authUserId;

                                    $canManageBooking = $reservation && $isAdmin;

                                    $secondaryLabel = $isLoggedIn ? $reservation?->booking?->player_names_label : null;

                                    $cellClass = 'cc-over';
                                    $slotClass = $isPastSlot ? ' slot-cell--past' : '';
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
                                                : '';
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
                                            <a href="{{ $eventEditUrl }}" class="event-label event-edit-trigger" data-edit-url="{{ $eventEditUrl }}">{{ $evBlock['name'] }}</a>
                                        @else
                                            <span class="event-label">{{ $evBlock['name'] }}</span>
                                        @endif
                                    </td>
                                @elseif($action === 'book' || $action === 'admin-book')
                                    <td @class(['cal-extra-day' => $extraDay]) data-day="{{ $dayIndex }}">
                                        <a href="#"
                                           class="calendar-cell {{ $cellClass }}{{ $slotClass }} booking-trigger"
                                           title="{{ $cellTitle }}"
                                           data-action="{{ $action }}"
                                           data-sid="{{ $sid }}"
                                           data-date="{{ $dateKey }}"
                                           data-time-start="{{ $h * 3600 }}"
                                           data-time-end="{{ ($h + 1) * 3600 }}"
                                           data-square-name="{{ $squareLabel }}"
                                           data-date-label="{{ $dateLabels[$d->format('Y-m-d')]['full'] }}"
                                           data-time-label="{{ $timeLabel }} – {{ $nextLabel }} Uhr"
                                           @if($isAdmin)
                                               data-create-url="{{ route('admin.bookings.create') }}?sid={{ $sid }}&date={{ $dateKey }}&time_start={{ $h * 3600 }}&time_end={{ ($h + 1) * 3600 }}"
                                           @endif></a>
                                    </td>
                                @elseif($action === 'cancel')
                                    <td @class(['cal-extra-day' => $extraDay]) data-day="{{ $dayIndex }}">
                                        <a href="#"
                                           class="calendar-cell {{ $cellClass }}{{ $slotClass }} booking-trigger"
                                           title="{{ $cellTitle }}"
                                           data-action="cancel"
                                           data-bid="{{ $reservation->booking->bid }}"
                                           data-square-name="{{ $squareLabel }}"
                                           data-date-label="{{ $dateLabels[$d->format('Y-m-d')]['full'] }}"
                                           data-time-label="{{ $timeLabel }} – {{ $nextLabel }} Uhr"
                                           @if($isAdmin)
                                               data-edit-url="{{ route('admin.bookings.edit', $reservation->booking) }}"
                                               data-delete-url="{{ route('admin.bookings.destroy', $reservation->booking) }}"
                                           @elseif($isOwn)
                                               data-edit-url="{{ route('bookings.edit', $reservation->booking) }}?popup=1"
                                           @endif>
                                            <span class="cc-label-primary">{{ $primaryLabel }}</span>
                                            @if($secondaryLabel)
                                                <span class="cc-label-secondary">{{ $secondaryLabel }}</span>
                                            @endif
                                        </a>
                                    </td>
                                @elseif($action === 'login')
                                    <td @class(['cal-extra-day' => $extraDay]) data-day="{{ $dayIndex }}">
                                        <a href="{{ route('login', ['redirect_to' => route('calendar.index', ['date' => $date->format('Y-m-d')])]) }}"
                                           class="calendar-cell {{ $cellClass }}{{ $slotClass }} guest-login-cell"
                                           title="{{ $cellTitle }}"></a>
                                    </td>
                                @else
                                    <td @class(['cal-extra-day' => $extraDay]) data-day="{{ $dayIndex }}">
                                        <span class="calendar-cell {{ $cellClass }}{{ $slotClass }}" title="{{ $cellTitle }}">
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
                        @foreach($squares as $square)
                            @php
                                $squareAlias = $square->display_name !== $square->name ? $square->display_name : null;
                            @endphp
                            <td @class(['square-head-cell', 'cal-extra-day' => $dayIndex >= 3]) data-day="{{ $dayIndex }}">
                                <span class="square-head-title">{{ $square->name }}</span>
                                @if($squareAlias)
                                    <span class="square-head-alias">{{ $squareAlias }}</span>
                                @endif
                            </td>
                        @endforeach
                    @endforeach
                </tr>
                <tr class="calendar-date-row footer-date-row">
                    <td class="time-spacer">&nbsp;</td>
                    @foreach($dates as $dayIndex => $d)
                        <td colspan="{{ $squares->count() }}" @class(['day-header-cell', 'cal-extra-day' => $dayIndex >= 3]) data-day="{{ $dayIndex }}">
                            <div class="day-header-inline"><span class="day-header-name">{{ $dateLabels[$d->format('Y-m-d')]['short'] }}</span><span class="day-header-date">{{ $dateLabels[$d->format('Y-m-d')]['long'] }}</span></div>
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<x-calendar.modals :date="$date" :squares="$squares" />
@endsection

@push('scripts')
<script>
(function () {
    var grid = document.getElementById('calendar-grid');
    if (!grid) { return; }

    var wrap = grid.closest('.calendar-wrap') || grid.parentElement;
    var squares = parseInt(grid.dataset.squares, 10) || 1;
    var maxDays = parseInt(grid.dataset.maxDays, 10) || 3;
    var BASE_DAYS = 3;
    var extras = grid.querySelectorAll('.cal-extra-day');

    function cssPx(name, fallback) {
        var value = parseFloat(getComputedStyle(grid).getPropertyValue(name));
        return isNaN(value) ? fallback : value;
    }

    function visibleDayCount() {
        // A day's width is ALL of its courts together. Using the full day width with
        // Math.floor means an extra day is revealed only when every court of that day
        // fits — a day that would be partially cut off is never shown.
        var dayWidth = squares * cssPx('--calendar-slot-col', 136);
        if (dayWidth <= 0) { return BASE_DAYS; }

        var fits = Math.floor((wrap.clientWidth - cssPx('--calendar-time-col', 94)) / dayWidth);
        return Math.max(BASE_DAYS, Math.min(maxDays, fits));
    }

    function applyVisibleDays() {
        var count = visibleDayCount();
        extras.forEach(function (el) {
            el.classList.toggle('is-visible', parseInt(el.getAttribute('data-day'), 10) < count);
        });
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




