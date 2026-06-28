@extends('layouts.app')
@section('title', __('booking.calendar.title', ['date' => $date->format('d.m.Y')]))

@push('header-nav')
<a href="{{ route('calendar.index', ['date' => $date->copy()->subDay()->format('Y-m-d')]) }}"
   class="default-button nav-arrow"
   title="{{ __('booking.nav.previous_day') }}">&#9664;</a>

<a href="{{ route('calendar.index') }}" class="default-button">{{ __('booking.nav.today') }}</a>

<form method="GET" action="{{ route('calendar.index') }}" class="date-switcher-form">
    <input type="date" name="date" id="c-date"
           value="{{ $date->format('Y-m-d') }}"
           class="date-switcher-input date-switcher-input--native"
           aria-label="{{ __('booking.nav.choose_date') }}">
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

<div id="cancel-modal" class="booking-modal" style="display:none;">
    <div class="booking-modal__viewport">
        <div class="booking-modal__card">
            <button id="cancel-modal-close" class="booking-modal__close" title="{{ __('booking.feedback.close') }}">&#x2715;</button>
            <div class="booking-modal__header">
                <h2 id="cancel-modal-title"></h2>
            </div>
            <div class="booking-modal__body">
                <div id="cancel-modal-date" class="booking-modal__meta"></div>
                <div id="cancel-modal-time" class="booking-modal__meta"></div>
                <p class="booking-modal__warning">{{ __('booking.modal.confirm_cancel') }}</p>
            </div>
            <div class="booking-modal__actions booking-modal__actions--manage">
                <a id="cancel-modal-edit" href="#" class="default-button" hidden>{{ __('booking.modal.edit') }}</a>
                <form id="cancel-form" method="POST" action="" class="booking-modal__action-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="modal-danger-button">{{ __('booking.modal.cancel_booking') }}</button>
                </form>
                @can('admin.booking')
                <form id="delete-form" method="POST" action="" class="booking-modal__action-form" onsubmit="return confirm('{{ __('booking.admin.bookings.confirm_delete') }}')" hidden>
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="redirect_to" value="{{ route('calendar.index', ['date' => $date->format('Y-m-d')]) }}">
                    <button type="submit" class="modal-danger-button modal-danger-button--delete">{{ __('booking.admin.bookings.delete_permanent') }}</button>
                </form>
                @endcan
                <button type="button" id="cancel-modal-abort" class="default-button">{{ __('booking.modal.cancel') }}</button>
            </div>
        </div>
    </div>
</div>

<div id="booking-modal" class="booking-modal" style="display:none;">
    <div class="booking-modal__viewport">
        <div class="booking-modal__card">
            <button id="modal-close" class="booking-modal__close" title="{{ __('booking.feedback.close') }}">&#x2715;</button>
            <div class="booking-modal__header">
                <h2 id="modal-title"></h2>
            </div>
            <div class="booking-modal__body">
                <div id="modal-date" class="booking-modal__meta"></div>
                <div id="modal-time" class="booking-modal__meta"></div>
                <p class="booking-modal__success">{{ __('booking.modal.slot_free') }}</p>
            </div>
            <form method="POST" action="{{ route('bookings.store') }}" class="booking-modal__actions booking-modal__actions--stacked">
                @csrf
                <input type="hidden" id="modal-sid" name="sid">
                <input type="hidden" id="modal-date-input" name="date">
                <input type="hidden" id="modal-ts" name="time_start">
                <input type="hidden" id="modal-te" name="time_end">

                <label class="booking-modal__field">
                    <span class="booking-modal__field-label">{{ __('booking.modal.play_type') }}</span>
                    <select id="modal-quantity" name="quantity" class="booking-modal__select">
                        <option value="2">{{ __('booking.modal.single') }}</option>
                        <option value="4">{{ __('booking.modal.double') }}</option>
                    </select>
                </label>

                <label class="booking-modal__field booking-modal__field--player" id="modal-player2-field" hidden>
                    <span class="booking-modal__field-label">{{ __('booking.modal.player_name_2') }}</span>
                    <input type="text" id="modal-player2" name="player_name_2" class="booking-modal__input" list="player-suggestions" maxlength="120" placeholder="{{ __('booking.modal.player_name_2_placeholder') }}" required>
                </label>

                <label class="booking-modal__field booking-modal__field--player" id="modal-player3-field" hidden>
                    <span class="booking-modal__field-label">{{ __('booking.modal.player_name_3') }}</span>
                    <input type="text" id="modal-player3" name="player_name_3" class="booking-modal__input" list="player-suggestions" maxlength="120" placeholder="{{ __('booking.modal.player_name_3_placeholder') }}">
                </label>

                <label class="booking-modal__field booking-modal__field--player" id="modal-player4-field" hidden>
                    <span class="booking-modal__field-label">{{ __('booking.modal.player_name_4') }}</span>
                    <input type="text" id="modal-player4" name="player_name_4" class="booking-modal__input" list="player-suggestions" maxlength="120" placeholder="{{ __('booking.modal.player_name_4_placeholder') }}">
                </label>

                <datalist id="player-suggestions"></datalist>

                @can('admin.event')
                    <button type="button" id="modal-create-event" class="default-button">{{ __('booking.modal.create_event') }}</button>
                @endcan
                <button type="submit" class="modal-primary-button">{{ __('booking.modal.book_now') }}</button>
                <button type="button" id="modal-cancel" class="default-button">{{ __('booking.modal.cancel') }}</button>
            </form>
        </div>
    </div>
</div>

@if(auth()->user()?->can('admin.booking') || auth()->user()?->can('admin.event'))
<div id="admin-booking-modal" class="booking-modal booking-modal--iframe" style="display:none;">
    <div class="booking-modal__viewport booking-modal__viewport--iframe">
        <div class="booking-modal__card booking-modal__card--iframe">
            <button id="abm-close" class="booking-modal__close booking-modal__close--iframe" title="{{ __('booking.feedback.close') }}">&#x2715;</button>
            <iframe id="abm-iframe" src="" class="booking-modal__iframe" allowfullscreen></iframe>
        </div>
    </div>
</div>
@endif

@can('admin.event')
<div id="event-modal" class="booking-modal" style="display:none;">
    <div class="booking-modal__viewport">
        <div class="booking-modal__card booking-modal__card--event">
            <button id="event-modal-close" class="booking-modal__close" title="{{ __('booking.feedback.close') }}">&#x2715;</button>
            <div class="booking-modal__header">
                <h2>{{ __('booking.modal.create_event') }}</h2>
            </div>
            <form id="event-form" method="POST" action="{{ route('admin.events.store') }}" class="booking-modal__body booking-modal__body--event">
                @csrf
                <input type="hidden" name="status" value="enabled">
                <input type="hidden" name="redirect_to" value="{{ route('calendar.index', ['date' => $date->format('Y-m-d')]) }}">
                <input type="hidden" name="datetime_start" id="event-datetime-start">
                <input type="hidden" name="datetime_end" id="event-datetime-end">

                <label class="booking-modal__field">
                    <span class="booking-modal__field-label">{{ __('booking.modal.event_name') }}</span>
                    <input type="text" name="name" id="event-name" class="booking-modal__input" maxlength="128" required>
                </label>

                <div class="booking-modal__event-grid">
                    <label class="booking-modal__field">
                        <span class="booking-modal__field-label">{{ __('booking.modal.date_start') }}</span>
                        <input type="date" id="event-date-start" class="booking-modal__input" required>
                    </label>
                    <label class="booking-modal__field">
                        <span class="booking-modal__field-label">{{ __('booking.modal.time_start') }}</span>
                        <input type="time" id="event-time-start" class="booking-modal__input" required>
                    </label>
                    <label class="booking-modal__field">
                        <span class="booking-modal__field-label">{{ __('booking.modal.date_end') }}</span>
                        <input type="date" id="event-date-end" class="booking-modal__input" required>
                    </label>
                    <label class="booking-modal__field">
                        <span class="booking-modal__field-label">{{ __('booking.modal.time_end') }}</span>
                        <input type="time" id="event-time-end" class="booking-modal__input" required>
                    </label>
                </div>

                <label class="booking-modal__field">
                    <span class="booking-modal__field-label">{{ __('booking.modal.event_description') }}</span>
                    <textarea name="description" id="event-description" class="booking-modal__input booking-modal__textarea" rows="3" maxlength="4096"></textarea>
                </label>

                <label class="booking-modal__field">
                    <span class="booking-modal__field-label">{{ __('booking.calendar.court') }}</span>
                    <select name="sid" id="event-sid" class="booking-modal__select">
                        <option value="">{{ __('booking.admin.events.all_courts') }}</option>
                        @foreach($squares as $square)
                            <option value="{{ $square->sid }}">{{ $square->display_name }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="booking-modal__actions booking-modal__actions--stacked booking-modal__actions--event">
                    <button type="submit" class="modal-primary-button">{{ __('booking.modal.save_event') }}</button>
                    <button type="button" id="event-modal-cancel" class="default-button">{{ __('booking.modal.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
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




