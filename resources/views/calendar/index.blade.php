@extends('layouts.app')
@section('title', 'Buchungsplan – ' . $date->format('d.m.Y'))

@push('header-nav')
<a href="{{ route('calendar.index', ['date' => $date->copy()->subDay()->format('Y-m-d')]) }}"
   class="default-button nav-arrow"
   title="Vorheriger Tag">&#9664;</a>

<a href="{{ route('calendar.index') }}" class="default-button">Heute</a>

<form method="GET" action="{{ route('calendar.index') }}" class="date-switcher-form">
    <input type="date" name="date" id="c-date"
           value="{{ $date->format('Y-m-d') }}"
           class="date-switcher-input date-switcher-input--native"
           aria-label="Datum wählen">
</form>

<a href="{{ route('calendar.index', ['date' => $date->copy()->addDay()->format('Y-m-d')]) }}"
   class="default-button nav-arrow"
   title="Nächster Tag">&#9654;</a>
@endpush

@section('calendar-system-info')
<div class="help-panel__grid help-panel__grid--single">
    <section class="help-card">
        <p class="help-card__eyebrow">Buchungssystem</p>
        <h2 class="help-card__title">Informationen</h2>
        <p class="help-card__text">
            Das Reservierungssystem zeigt immer drei Tage gleichzeitig und alle drei Plaetze vollstaendig an.
            Freie Zeiten koennen direkt im Plan gewaehlt werden.
        </p>
        <ul class="help-card__list">
            <li>Weisse Felder sind sofort buchbar.</li>
            <li>Blaue Felder sind eigene oder bestehende Reservierungen.</li>
            <li>Rosafarbene Flaechen markieren Veranstaltungen oder Sperren.</li>
            <li>Graue Felder liegen in der Vergangenheit und sind nicht mehr buchbar.</li>
            <li>Mit den Pfeilen oben wechselt der Plan tageweise.</li>
        </ul>
    </section>
</div>
@endsection

@section('calendar-help')
<div class="help-panel__grid">
    @auth
        <section class="help-card">
            <p class="help-card__eyebrow">Mein Bereich</p>
            <h2 class="help-card__title">{{ auth()->user()->name }}</h2>
            <p class="help-card__text">
                Freie Felder lassen sich direkt anklicken. Eigene Reservierungen erscheinen blau
                und können aus dem Plan heraus storniert werden.
            </p>
            <div class="help-card__status">
                <span class="help-card__status-label">Status</span>
                <strong>Mitglied angemeldet</strong>
            </div>
        </section>
    @endauth

    <section class="help-card">
        <p class="help-card__eyebrow">Hinweise</p>
        <h2 class="help-card__title">So funktioniert's</h2>
        <ul class="help-card__list">
            <li>Freie Felder sind weiß markiert.</li>
            <li>Graue Felder sind bereits belegt oder liegen in der Vergangenheit.</li>
            <li>Rosafarbene Blöcke kennzeichnen Veranstaltungen oder Sperren.</li>
            <li>Navigation oben wechselt zwischen den angezeigten Tagen.</li>
        </ul>
    </section>
</div>
@endsection

@section('content')
<div class="calendar-layout">
    <div class="calendar-wrap">
        <table class="calendar-square-table booking-grid">
            <colgroup>
                <col class="calendar-time-col">
                @foreach($dates as $d)
                    @foreach($squares as $square)
                        <col class="calendar-slot-col">
                    @endforeach
                @endforeach
            </colgroup>
            <thead>
                <tr class="calendar-date-row">
                    <td class="time-spacer">&nbsp;</td>
                    @foreach($dates as $d)
                        <td colspan="{{ $squares->count() }}" class="day-header-cell">
                            <div class="day-header-inline"><span class="day-header-name">{{ $d->isoFormat('dddd') }}</span><span class="day-header-date">{{ $d->isoFormat('D. MMMM YYYY') }}</span></div>
                        </td>
                    @endforeach
                </tr>
                <tr class="calendar-square-row">
                    <td class="time-side-head">Platz</td>
                    @foreach($dates as $d)
                        @foreach($squares as $square)
                            @php
                                $squareAlias = $square->display_name !== $square->name ? $square->display_name : null;
                            @endphp
                            <td class="square-head-cell">
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
                            <span class="time-sub">bis {{ $nextLabel }} Uhr</span>
                        </td>

                        @foreach($dates as $d)
                            @php
                                $dateKey = $d->format('Y-m-d');
                                $slotStart = $d->copy()->startOfDay()->addHours($h);
                                $slotEnd = $slotStart->copy()->addHour();
                                $isPastSlot = $slotEnd->isPast();
                            @endphp
                            @foreach($squares as $square)
                                @php
                                    $sid = $square->sid;
                                    $squareLabel = $square->display_name;

                                    if (!empty($eventSkip[$dateKey][$sid][$h])) { continue; }

                                    $evBlock = $eventBlocks[$dateKey][$sid][$h] ?? null;
                                    $reservation = $reservationsBySlot[$dateKey][$sid][$h] ?? null;

                                    $isOwn = $reservation
                                        && auth()->check()
                                        && isset($reservation->booking->user)
                                        && $reservation->booking->user->uid === auth()->id();

                                    $canManageBooking = $reservation
                                        && auth()->check()
                                        && auth()->user()->can('admin.booking');

                                    $secondaryLabel = auth()->check() ? $reservation?->booking?->player_names_label : null;

                                    $cellClass = 'cc-over';
                                    $slotClass = $isPastSlot ? ' slot-cell--past' : '';
                                    $primaryLabel = '';
                                    $action = null;
                                    $cellTitle = $squareLabel . ' – Vergangen';

                                    if (!$evBlock) {
                                        if ($isPastSlot && !$reservation) {
                                            $cellClass = 'cc-over';
                                            $cellTitle = $squareLabel . ' – Vergangen';
                                        } elseif (!$reservation) {
                                            $cellClass = 'cc-free';
                                            $action = auth()->check() && !$isPastSlot ? (auth()->user()->can('admin.booking') ? 'admin-book' : 'book') : 'login';
                                            $cellTitle = auth()->check()
                                                ? ($squareLabel . ' um ' . $timeLabel . ' buchen')
                                                : 'Zum Buchen bitte anmelden';
                                        } elseif ($isOwn || ($canManageBooking && !$isPastSlot)) {
                                            $cellClass = $isOwn ? 'cc-own' : 'cc-single-future';
                                            $action = 'cancel';
                                            $primaryLabel = $reservation->booking->owner_label;
                                            $cellTitle = $isOwn
                                                ? 'Buchung auf ' . $squareLabel . ' stornieren'
                                                : 'Buchung auf ' . $squareLabel . ' bearbeiten';
                                        } else {
                                            $cellClass = 'cc-single-future';
                                            $primaryLabel = auth()->check()
                                                ? ($reservation->booking?->owner_label ?? 'Belegt')
                                                : '';
                                            $cellTitle = $isPastSlot
                                                ? ($squareLabel . ' – Vergangen')
                                                : ($squareLabel . ' – Belegt');
                                        }
                                    }
                                @endphp

                                @if($evBlock)
                                    <td rowspan="{{ $evBlock['rows'] }}" colspan="{{ $evBlock['cols'] }}" class="event-cell" title="{{ $evBlock['name'] }}">
                                        <span class="event-label">{{ $evBlock['name'] }}</span>
                                    </td>
                                @elseif($action === 'book' || $action === 'admin-book')
                                    <td>
                                        <a href="#"
                                           class="calendar-cell {{ $cellClass }}{{ $slotClass }} booking-trigger"
                                           title="{{ $cellTitle }}"
                                           data-action="{{ $action }}"
                                           data-sid="{{ $sid }}"
                                           data-date="{{ $dateKey }}"
                                           data-time-start="{{ $h * 3600 }}"
                                           data-time-end="{{ ($h + 1) * 3600 }}"
                                           data-square-name="{{ $squareLabel }}"
                                           data-date-label="{{ $d->isoFormat('dddd, D. MMMM YYYY') }}"
                                           data-time-label="{{ $timeLabel }} – {{ $nextLabel }} Uhr"
                                           @if(auth()->check() && auth()->user()->can('admin.booking'))
                                               data-create-url="{{ route('admin.bookings.create') }}?sid={{ $sid }}&date={{ $dateKey }}&time_start={{ $h * 3600 }}&time_end={{ ($h + 1) * 3600 }}"
                                           @endif></a>
                                    </td>
                                @elseif($action === 'cancel')
                                    <td>
                                        <a href="#"
                                           class="calendar-cell {{ $cellClass }}{{ $slotClass }} booking-trigger"
                                           title="{{ $cellTitle }}"
                                           data-action="cancel"
                                           data-bid="{{ $reservation->booking->bid }}"
                                           data-square-name="{{ $squareLabel }}"
                                           data-date-label="{{ $d->isoFormat('dddd, D. MMMM YYYY') }}"
                                           data-time-label="{{ $timeLabel }} – {{ $nextLabel }} Uhr"
                                           @if(auth()->check() && auth()->user()->can('admin.booking'))
                                               data-edit-url="{{ route('admin.bookings.edit', $reservation->booking) }}"
                                           @endif>
                                            <span class="cc-label-primary">{{ $primaryLabel }}</span>
                                            @if($secondaryLabel)
                                                <span class="cc-label-secondary">{{ $secondaryLabel }}</span>
                                            @endif
                                        </a>
                                    </td>
                                @elseif($action === 'login')
                                    <td>
                                        <a href="{{ route('login', ['redirect_to' => route('calendar.index', ['date' => $date->format('Y-m-d')])]) }}"
                                           class="calendar-cell {{ $cellClass }}{{ $slotClass }} guest-login-cell"
                                           title="{{ $cellTitle }}"></a>
                                    </td>
                                @else
                                    <td>
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
                    <td class="time-side-head">Platz</td>
                    @foreach($dates as $d)
                        @foreach($squares as $square)
                            @php
                                $squareAlias = $square->display_name !== $square->name ? $square->display_name : null;
                            @endphp
                            <td class="square-head-cell">
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
                    @foreach($dates as $d)
                        <td colspan="{{ $squares->count() }}" class="day-header-cell">
                            <div class="day-header-inline"><span class="day-header-name">{{ $d->isoFormat('dddd') }}</span><span class="day-header-date">{{ $d->isoFormat('D. MMMM YYYY') }}</span></div>
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
            <button id="cancel-modal-close" class="booking-modal__close" title="Schließen">&#x2715;</button>
            <div class="booking-modal__header">
                <h2 id="cancel-modal-title"></h2>
            </div>
            <div class="booking-modal__body">
                <div id="cancel-modal-date" class="booking-modal__meta"></div>
                <div id="cancel-modal-time" class="booking-modal__meta"></div>
                <p class="booking-modal__warning">Möchten Sie diese Buchung stornieren?</p>
            </div>
            <form id="cancel-form" method="POST" action="" class="booking-modal__actions">
                @csrf
                @method('DELETE')
                <a id="cancel-modal-edit" href="#" class="default-button" hidden>Bearbeiten</a>
                <button type="submit" class="modal-danger-button">Stornieren</button>
                <button type="button" id="cancel-modal-abort" class="default-button">Abbrechen</button>
            </form>
        </div>
    </div>
</div>

<div id="booking-modal" class="booking-modal" style="display:none;">
    <div class="booking-modal__viewport">
        <div class="booking-modal__card">
            <button id="modal-close" class="booking-modal__close" title="Schließen">&#x2715;</button>
            <div class="booking-modal__header">
                <h2 id="modal-title"></h2>
            </div>
            <div class="booking-modal__body">
                <div id="modal-date" class="booking-modal__meta"></div>
                <div id="modal-time" class="booking-modal__meta"></div>
                <p class="booking-modal__success">Dieser Platz ist noch frei.</p>
            </div>
            <form method="POST" action="{{ route('bookings.store') }}" class="booking-modal__actions booking-modal__actions--stacked">
                @csrf
                <input type="hidden" id="modal-sid" name="sid">
                <input type="hidden" id="modal-date-input" name="date">
                <input type="hidden" id="modal-ts" name="time_start">
                <input type="hidden" id="modal-te" name="time_end">

                <label class="booking-modal__field">
                    <span class="booking-modal__field-label">Spielart</span>
                    <select id="modal-quantity" name="quantity" class="booking-modal__select">
                        <option value="2">Einzel</option>
                        <option value="4">Doppel</option>
                    </select>
                </label>

                <label class="booking-modal__field booking-modal__field--player" id="modal-player2-field" hidden>
                    <span class="booking-modal__field-label">2. Spielername</span>
                    <input type="text" id="modal-player2" name="player_name_2" class="booking-modal__input" list="player-suggestions" maxlength="120" placeholder="Name des 2. Spielers" required>
                </label>

                <label class="booking-modal__field booking-modal__field--player" id="modal-player3-field" hidden>
                    <span class="booking-modal__field-label">3. Spielername</span>
                    <input type="text" id="modal-player3" name="player_name_3" class="booking-modal__input" list="player-suggestions" maxlength="120" placeholder="Name des 3. Spielers">
                </label>

                <label class="booking-modal__field booking-modal__field--player" id="modal-player4-field" hidden>
                    <span class="booking-modal__field-label">4. Spielername</span>
                    <input type="text" id="modal-player4" name="player_name_4" class="booking-modal__input" list="player-suggestions" maxlength="120" placeholder="Name des 4. Spielers">
                </label>

                <datalist id="player-suggestions"></datalist>

                @can('admin.event')
                    <button type="button" id="modal-create-event" class="default-button">Veranstaltung anlegen</button>
                @endcan
                <button type="submit" class="modal-primary-button">Jetzt buchen</button>
                <button type="button" id="modal-cancel" class="default-button">Abbrechen</button>
            </form>
        </div>
    </div>
</div>

@can('admin.event')
<div id="event-modal" class="booking-modal" style="display:none;">
    <div class="booking-modal__viewport">
        <div class="booking-modal__card booking-modal__card--event">
            <button id="event-modal-close" class="booking-modal__close" title="Schließen">&#x2715;</button>
            <div class="booking-modal__header">
                <h2>Veranstaltung anlegen</h2>
            </div>
            <form id="event-form" method="POST" action="{{ route('admin.events.store') }}" class="booking-modal__body booking-modal__body--event">
                @csrf
                <input type="hidden" name="status" value="enabled">
                <input type="hidden" name="redirect_to" value="{{ route('calendar.index', ['date' => $date->format('Y-m-d')]) }}">
                <input type="hidden" name="datetime_start" id="event-datetime-start">
                <input type="hidden" name="datetime_end" id="event-datetime-end">

                <label class="booking-modal__field">
                    <span class="booking-modal__field-label">Name</span>
                    <input type="text" name="name" id="event-name" class="booking-modal__input" maxlength="128" required>
                </label>

                <div class="booking-modal__event-grid">
                    <label class="booking-modal__field">
                        <span class="booking-modal__field-label">Datum (Beginn)</span>
                        <input type="date" id="event-date-start" class="booking-modal__input" required>
                    </label>
                    <label class="booking-modal__field">
                        <span class="booking-modal__field-label">Zeit (Beginn)</span>
                        <input type="time" id="event-time-start" class="booking-modal__input" required>
                    </label>
                    <label class="booking-modal__field">
                        <span class="booking-modal__field-label">Datum (Ende)</span>
                        <input type="date" id="event-date-end" class="booking-modal__input" required>
                    </label>
                    <label class="booking-modal__field">
                        <span class="booking-modal__field-label">Zeit (Ende)</span>
                        <input type="time" id="event-time-end" class="booking-modal__input" required>
                    </label>
                </div>

                <label class="booking-modal__field">
                    <span class="booking-modal__field-label">Platz</span>
                    <select name="sid" id="event-sid" class="booking-modal__select">
                        @foreach($squares as $square)
                            <option value="{{ $square->sid }}">{{ $square->display_name }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="booking-modal__actions booking-modal__actions--stacked booking-modal__actions--event">
                    <button type="submit" class="modal-primary-button">Veranstaltung speichern</button>
                    <button type="button" id="event-modal-cancel" class="default-button">Abbrechen</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endsection




