@php
    $bookingUser = $booking->user;
    $createdLabel = $booking->created ? \Carbon\Carbon::parse($booking->created)->format('d.m.Y \u\m H:i \U\h\r') : __('booking.admin.bookings.not_saved_yet');
    $ownerName = trim((string) ($booking->meta->firstWhere('key', 'owner-name')?->value ?? ''));
    $isMemberOwner = $ownerName === '' && $bookingUser !== null;
    $closeRoute = route('admin.bookings.index');
    $calendarReturn = route('calendar.index', ['date' => old('date', $reservation?->date ?? now()->format('Y-m-d'))]);
    $courtName = $booking->square?->display_name;
    $headTitle = collect([trim((string) $bookedFor), $courtName])->filter(fn ($part) => $part !== null && $part !== '')->implode(' · ');
    $statusBadge = [
        'single' => ['active', __('booking.admin.bookings.status_active')],
        'subscription' => ['subscription', __('booking.admin.bookings.status_series')],
        'cancelled' => ['cancelled', __('booking.admin.bookings.status_cancelled')],
    ][$booking->status] ?? null;
    $currentQuantity = (int) old('quantity', $booking->quantity);
    // Player exchange: a running search or joined members lock date, time, repeat and booker.
    $exchangeLocked = (bool) ($exchangeLocked ?? false);
    $exchangeJoined = $exchangeJoined ?? [];
    $lockedAttrs = $exchangeLocked ? ' readonly aria-describedby="admin-booking-exchange-notice"' : '';
@endphp
<div class="ds-admin-stack ds-admin-booking">
    <x-admin.page-header :title="$headTitle !== '' ? $headTitle : __('booking.admin.bookings.edit_title')" :back-url="route('admin.bookings.index')" :back-label="__('booking.admin.bookings.title')">
        @if ($statusBadge !== null)
            <x-ui.badge :status="$statusBadge[0]">{{ $statusBadge[1] }}</x-ui.badge>
        @endif
        @if ($occurrenceDate)
            <span>{{ __('booking.admin.bookings.occurrence_of', ['date' => \Carbon\Carbon::parse($occurrenceDate)->format('d.m.Y')]) }}</span>
        @endif
    </x-admin.page-header>

    @if ($errors->any())
        <x-ui.notice tone="problem">
            <ul class="ds-admin-errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.notice>
    @endif

    @if ($exchangeLocked)
        <x-ui.notice tone="neutral" id="admin-booking-exchange-notice">
            {{ __('booking.admin.bookings.exchange_notice') }}
            @if ($exchangeJoined !== [])
                <br>{{ __('booking.admin.bookings.exchange_joined', ['names' => implode(', ', $exchangeJoined)]) }}
            @endif
        </x-ui.notice>
    @endif

    <form method="POST" action="{{ route('admin.bookings.update', $booking) }}" id="admin-booking-update" class="ds-admin-stack ds-admin-booking__form" data-exchange-locked="{{ $exchangeLocked ? '1' : '0' }}">
        @csrf
        @method('PUT')
        @if (request('popup'))
            <input type="hidden" name="popup" value="1">
            <input type="hidden" name="redirect_to" value="{{ $calendarReturn }}">
        @endif
        <input type="hidden" name="occurrence" value="{{ $occurrenceDate }}">

        <div class="ds-admin-grid">
            <div class="ds-admin-grid__main ds-admin-form-cards">
                <x-ui.card :title="__('booking.admin.bookings.section_who')" padding="lg" class="ds-admin-form-card">
                    <div class="ds-admin-form-grid">
                        <x-ui.field :label="__('booking.admin.bookings.booked_for')" for="booked_for">
                            <input type="text" id="booked_for" name="booked_for" value="{{ old('booked_for', $bookedFor) }}" list="admin-player-suggestions" maxlength="120" required class="ds-input{{ $exchangeLocked ? ' is-disabled' : '' }}"{!! $lockedAttrs !!}>
                            @if ($isMemberOwner && Route::has('admin.users.edit') && auth()->user()->can('admin.user'))
                                <a href="{{ route('admin.users.edit', $bookingUser) }}" class="ds-admin-link ds-admin-field-link">{{ __('booking.admin.bookings.edit_user') }}</a>
                            @endif
                        </x-ui.field>
                        <x-ui.field :label="__('booking.admin.common.court')" for="sid">
                            <select name="sid" id="sid" class="ds-select">
                                @foreach ($squares as $square)
                                    <option value="{{ $square->sid }}" @selected(old('sid', $booking->sid) == $square->sid)>{{ $square->name }} {{ $square->display_name }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                        <x-ui.option-group :legend="__('booking.admin.bookings.player_count')">
                            <x-ui.option-tile name="quantity" value="2" :label="__('booking.modal.single_option')" :checked="$currentQuantity === 2" id="admin-booking-quantity-2" />
                            <x-ui.option-tile name="quantity" value="4" :label="__('booking.modal.double_option')" :checked="$currentQuantity === 4" id="admin-booking-quantity-4" />
                        </x-ui.option-group>
                        <x-ui.field :label="__('booking.admin.bookings.teammates')" for="admin-player2">
                            <input type="text" id="admin-player2" name="player_name_2" value="{{ old('player_name_2', $playerNames[2]) }}" list="admin-player-suggestions" maxlength="120"{{ $exchangeLocked ? '' : ' required' }} class="ds-input">
                        </x-ui.field>
                        <x-ui.field :label="__('booking.admin.bookings.player_name_3')" for="admin-player3">
                            <input type="text" id="admin-player3" name="player_name_3" value="{{ old('player_name_3', $playerNames[3]) }}" list="admin-player-suggestions" maxlength="120" class="ds-input">
                        </x-ui.field>
                        <x-ui.field :label="__('booking.admin.bookings.player_name_4')" for="admin-player4">
                            <input type="text" id="admin-player4" name="player_name_4" value="{{ old('player_name_4', $playerNames[4]) }}" list="admin-player-suggestions" maxlength="120" class="ds-input">
                        </x-ui.field>
                    </div>
                    <datalist id="admin-player-suggestions">
                        @foreach ($users as $user)
                            <option value="{{ $user->alias }}"></option>
                        @endforeach
                    </datalist>
                </x-ui.card>

                <x-ui.card :title="__('booking.admin.bookings.section_when')" padding="lg" class="ds-admin-form-card">
                    <div class="ds-admin-form-grid">
                        <x-ui.field :label="__('booking.admin.bookings.date_start')" for="date">
                            <input type="date" id="date" name="date" value="{{ old('date', $reservation?->date) }}" class="ds-input"{!! $lockedAttrs !!}>
                        </x-ui.field>
                        <div class="ds-admin-form-grid__pair">
                            <x-ui.field :label="__('booking.admin.bookings.time_start')" for="time_start">
                                <input type="time" id="time_start" name="time_start" value="{{ old('time_start', $reservation ? substr((string) $reservation->time_start, 0, 5) : '') }}" class="ds-input"{!! $lockedAttrs !!}>
                            </x-ui.field>
                            <x-ui.field :label="__('booking.admin.bookings.time_end')" for="time_end">
                                <input type="time" id="time_end" name="time_end" value="{{ old('time_end', $reservation ? substr((string) $reservation->time_end, 0, 5) : '') }}" class="ds-input"{!! $lockedAttrs !!}>
                            </x-ui.field>
                        </div>
                    </div>
                    <div class="ds-admin-form-grid ds-admin-form-grid--2">
                        <x-ui.field :label="__('booking.admin.bookings.repeat')" for="admin-booking-repeat">
                            {{-- Locked by a player search: a disabled select is not sent, so its value goes along hidden. --}}
                            <select name="repeat_type" id="admin-booking-repeat" class="ds-select"@if ($exchangeLocked) disabled aria-describedby="admin-booking-exchange-notice"@endif>
                                @foreach ($repeatOptions as $repeatValue => $repeatLabel)
                                    <option value="{{ $repeatValue }}" @selected(old('repeat_type', $repeatType) === $repeatValue)>{{ $repeatLabel }}</option>
                                @endforeach
                            </select>
                            @if ($exchangeLocked)
                                <input type="hidden" name="repeat_type" value="{{ $repeatType }}">
                            @endif
                        </x-ui.field>
                        <x-ui.field :label="__('booking.admin.bookings.date_end')" for="admin-booking-date-end">
                            <input type="date" id="admin-booking-date-end" name="date_end" value="{{ old('date_end', $repeatEndDate) }}" class="ds-input">
                        </x-ui.field>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('booking.admin.bookings.notes_heading')" padding="lg" class="ds-admin-form-card">
                    <x-ui.field for="admin_note" :help="__('booking.admin.bookings.note_hint')">
                        <textarea id="admin_note" name="admin_note" rows="4" class="ds-textarea" aria-label="{{ __('booking.admin.bookings.admin_note') }}" aria-describedby="admin_note-help">{{ old('admin_note', $adminNote) }}</textarea>
                    </x-ui.field>
                    @unless ($showScopeChoice)
                        <p class="ds-admin-note">{{ __('booking.admin.bookings.booking_created_at', ['date' => $createdLabel]) }}</p>
                    @endunless
                </x-ui.card>
            </div>

            @if ($showScopeChoice)
                <x-ui.card :title="__('booking.admin.bookings.scope_card_title')" class="ds-admin-grid__side ds-admin-scope">
                    <x-ui.option-group :legend="__('booking.admin.bookings.edit_scope_label')">
                        <x-ui.option-tile name="edit_scope" value="single_occurrence" :label="__('booking.admin.bookings.edit_scope_single', ['date' => \Carbon\Carbon::parse($occurrenceDate)->format('d.m.Y')])" :checked="true" />
                        <x-ui.option-tile name="edit_scope" value="this_and_following" :label="__('booking.admin.bookings.edit_scope_following')" />
                    </x-ui.option-group>
                    <p class="ds-admin-note ds-admin-scope__created">{{ __('booking.admin.bookings.booking_created_at', ['date' => $createdLabel]) }}</p>
                </x-ui.card>
            @endif
        </div>

        <div class="ds-admin-actions ds-admin-actions--sticky">
            <div class="ds-admin-actions__group">
                @if ($booking->status !== 'cancelled')
                    <x-ui.button variant="danger" type="submit" form="admin-booking-cancel" :block="false">{{ __('booking.admin.bookings.cancel_booking') }}</x-ui.button>
                @endif
                <x-ui.button variant="danger" type="submit" form="admin-booking-delete" :block="false">{{ __('booking.admin.common.delete') }}</x-ui.button>
            </div>
            <div class="ds-admin-actions__group ds-admin-actions__group--main">
                <x-ui.button variant="secondary" :href="$closeRoute" :block="false">{{ __('booking.admin.common.abort') }}</x-ui.button>
                <x-ui.button type="submit" :block="false" class="ds-admin-actions__save">{{ __('booking.admin.bookings.save_action') }}</x-ui.button>
            </div>
        </div>
    </form>

    @if ($booking->status !== 'cancelled')
        <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" id="admin-booking-cancel" hidden onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_cancel')) }})">
            @csrf
            <input type="hidden" name="occurrence" value="{{ $occurrenceDate }}">
            @if ($showScopeChoice)
                <input type="hidden" name="edit_scope" id="admin-booking-cancel-scope" value="single_occurrence">
            @endif
            @if (request('popup'))
                <input type="hidden" name="redirect_to" value="{{ $calendarReturn }}">
            @endif
        </form>
    @endif

    <form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}" id="admin-booking-delete" hidden onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_delete')) }})">
        @csrf
        @method('DELETE')
        <input type="hidden" name="occurrence" value="{{ $occurrenceDate }}">
        @if ($showScopeChoice)
            <input type="hidden" name="edit_scope" id="admin-booking-delete-scope" value="single_occurrence">
        @endif
        @if (request('popup'))
            <input type="hidden" name="redirect_to" value="{{ $calendarReturn }}">
        @endif
    </form>
</div>
