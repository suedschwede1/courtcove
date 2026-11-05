@php
    $closeRoute = route('calendar.index', ['date' => old('date', $reservation?->date)]);
    $calendarReturn = route('calendar.index', ['date' => old('date', $reservation?->date ?? now()->format('Y-m-d'))]);
    $currentQuantity = (int) old('quantity', $booking->quantity);
@endphp
<div class="ds-admin-stack ds-admin-booking" x-data="{ tab: 'booking' }">
    <x-admin.page-header :title="__('booking.admin.bookings.create_title')" :back-url="route('admin.bookings.index')" :back-label="__('booking.admin.bookings.title')" />

    <div role="tablist" class="ds-tabs" aria-label="{{ __('booking.admin.bookings.create_title') }}">
        <button type="button" role="tab" id="admin-create-tab-booking" class="ds-tabs__tab" aria-controls="admin-create-panel-booking" aria-selected="true" x-bind:aria-selected="tab === 'booking' ? 'true' : 'false'" x-on:click="tab = 'booking'">{{ __('booking.admin.bookings.booking_tab') }}</button>
        @can('admin.event')
            <button type="button" role="tab" id="admin-create-tab-event" class="ds-tabs__tab" aria-controls="admin-create-panel-event" aria-selected="false" x-bind:aria-selected="tab === 'event' ? 'true' : 'false'" x-on:click="tab = 'event'">{{ __('booking.admin.bookings.event_tab') }}</button>
        @endcan
    </div>

    <div role="tabpanel" id="admin-create-panel-booking" aria-labelledby="admin-create-tab-booking" x-show="tab === 'booking'">
        @if ($errors->any())
            <x-ui.notice tone="problem">
                <ul class="ds-admin-errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.notice>
        @endif

        <form method="POST" action="{{ route('admin.bookings.store') }}" id="admin-booking-create" class="ds-admin-stack ds-admin-booking__form">
            @csrf
            @if (request('popup'))
                <input type="hidden" name="popup" value="1">
                <input type="hidden" name="redirect_to" value="{{ $calendarReturn }}">
            @endif

            <div class="ds-admin-form-cards">
                <x-ui.card :title="__('booking.admin.bookings.section_who')" padding="lg" class="ds-admin-form-card">
                    <div class="ds-admin-form-grid ds-admin-form-grid--2">
                        <x-ui.field :label="__('booking.admin.common.court')" for="sid">
                            <select name="sid" id="sid" class="ds-select">
                                @foreach ($squares as $square)
                                    <option value="{{ $square->sid }}" @selected(old('sid', $booking->sid) == $square->sid)>{{ $square->name }} {{ $square->display_name }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                        <x-ui.field :label="__('booking.admin.bookings.booked_for')" for="booked_for">
                            <input type="text" id="booked_for" name="booked_for" value="{{ old('booked_for', $bookedFor) }}" list="admin-player-suggestions" maxlength="120" required placeholder="{{ __('booking.admin.bookings.member_search_placeholder') }}" class="ds-input">
                        </x-ui.field>
                    </div>
                    <x-ui.option-group :legend="__('booking.admin.bookings.player_count')">
                        <x-ui.option-tile name="quantity" value="2" :label="__('booking.modal.single_option')" :checked="$currentQuantity === 2" id="admin-booking-quantity-2" />
                        <x-ui.option-tile name="quantity" value="4" :label="__('booking.modal.double_option')" :checked="$currentQuantity === 4" id="admin-booking-quantity-4" />
                    </x-ui.option-group>
                    <div class="ds-field ds-admin-suggest"
                         x-data="{
                             quantity: '{{ $currentQuantity }}',
                             acResults: [],
                             acOpen: false,
                             async fetchAc(v) {
                                 if (v.length < 2) { this.acResults = []; this.acOpen = false; return; }
                                 const r = await fetch(@js(route('bookings.players')) + '?q=' + encodeURIComponent(v) + '&quantity=' + encodeURIComponent(this.quantity));
                                 this.acResults = await r.json();
                                 this.acOpen = this.acResults.length > 0;
                             }
                         }"
                         @change.document="if ($event.target.name === 'quantity') quantity = $event.target.value"
                         @click.outside="acOpen = false">
                        <label class="ds-field__label" for="admin-mitspieler">{{ __('booking.admin.bookings.teammates') }}</label>
                        <input type="text" id="admin-mitspieler" name="mitspieler" value="{{ old('mitspieler', $playerNames[2]) }}"
                               maxlength="255"
                               class="ds-input"
                               :placeholder="quantity === '2' ? @js(__('booking.modal.player_search_placeholder')) : @js(__('booking.admin.bookings.teammates_examples'))"
                               autocomplete="off"
                               @input.debounce.300ms="fetchAc($event.target.value)"
                               @focus="fetchAc($event.target.value)">
                        <ul x-show="acOpen" x-cloak class="ds-admin-suggest__list">
                            <template x-for="r in acResults" :key="r">
                                <li class="ds-admin-suggest__item" @mousedown.prevent="document.getElementById('admin-mitspieler').value = r; acResults = []; acOpen = false" x-text="r"></li>
                            </template>
                        </ul>
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
                            <input type="date" id="date" name="date" value="{{ old('date', $reservation?->date) }}" class="ds-input">
                        </x-ui.field>
                        <div class="ds-admin-form-grid__pair">
                            <x-ui.field :label="__('booking.admin.bookings.time_start')" for="time_start">
                                <input type="time" id="time_start" name="time_start" value="{{ old('time_start', $reservation ? substr((string) $reservation->time_start, 0, 5) : '') }}" class="ds-input">
                            </x-ui.field>
                            <x-ui.field :label="__('booking.admin.bookings.time_end')" for="time_end">
                                <input type="time" id="time_end" name="time_end" value="{{ old('time_end', $reservation ? substr((string) $reservation->time_end, 0, 5) : '') }}" class="ds-input">
                            </x-ui.field>
                        </div>
                    </div>
                    <div class="ds-admin-form-grid ds-admin-form-grid--2">
                        <x-ui.field :label="__('booking.admin.bookings.repeat')" for="admin-booking-repeat">
                            <select name="repeat_type" id="admin-booking-repeat" class="ds-select">
                                @foreach ($repeatOptions as $repeatValue => $repeatLabel)
                                    <option value="{{ $repeatValue }}" @selected(old('repeat_type', $repeatType) === $repeatValue)>{{ $repeatLabel }}</option>
                                @endforeach
                            </select>
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
                </x-ui.card>
            </div>

            <div class="ds-admin-actions ds-admin-actions--sticky">
                <div class="ds-admin-actions__group ds-admin-actions__group--main">
                    <x-ui.button variant="secondary" :href="$closeRoute" :block="false">{{ __('booking.admin.common.abort') }}</x-ui.button>
                    <x-ui.button type="submit" :block="false" class="ds-admin-actions__save">{{ __('booking.admin.bookings.save_action') }}</x-ui.button>
                </div>
            </div>
        </form>
    </div>

    @can('admin.event')
        <div role="tabpanel" id="admin-create-panel-event" aria-labelledby="admin-create-tab-event" x-show="tab === 'event'" x-cloak>
            <form method="POST" action="{{ route('admin.events.store') }}" id="admin-event-create" class="ds-admin-stack">
                @csrf
                @if (request('popup'))
                    <input type="hidden" name="popup" value="1">
                    <input type="hidden" name="redirect_to" value="{{ route('calendar.index') }}">
                @endif

                <x-ui.card padding="lg" class="ds-admin-form-card">
                    @include('admin.events._form', array_merge($eventFormData ?? [], ['squares' => $squares, 'popup_mode' => true]))
                </x-ui.card>

                <div class="ds-admin-actions ds-admin-actions--sticky">
                    <div class="ds-admin-actions__group ds-admin-actions__group--main">
                        <x-ui.button variant="secondary" :href="$closeRoute" :block="false">{{ __('booking.admin.common.abort') }}</x-ui.button>
                        <x-ui.button type="submit" :block="false" class="ds-admin-actions__save">{{ __('booking.admin.bookings.create_action') }}</x-ui.button>
                    </div>
                </div>
            </form>
        </div>
    @endcan
</div>
