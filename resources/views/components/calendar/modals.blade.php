@props([
    'date',
    'squares',
])

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

@auth
<div id="admin-booking-modal" class="booking-modal booking-modal--iframe" style="display:none;">
    <div class="booking-modal__viewport booking-modal__viewport--iframe">
        <div class="booking-modal__card booking-modal__card--iframe">
            <button id="abm-close" class="booking-modal__close booking-modal__close--iframe" title="{{ __('booking.feedback.close') }}">&#x2715;</button>
            <iframe id="abm-iframe" src="" class="booking-modal__iframe" allowfullscreen></iframe>
        </div>
    </div>
</div>
@endauth

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
