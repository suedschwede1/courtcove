@extends(request('popup') ? 'layouts.popup' : 'layouts.admin')
@section('title', ($isCreate ?? false) ? __('booking.admin.bookings.create_title') : __('booking.admin.bookings.edit_title'))
@section('admin-title', ($isCreate ?? false) ? __('booking.admin.bookings.create_title') : __('booking.admin.bookings.edit_title'))

@php
    $bookingUser = $booking->user;
    $createdLabel = $booking->created ? \Carbon\Carbon::parse($booking->created)->format('d.m.Y \u\m H:i \U\h\r') : __('booking.admin.bookings.not_saved_yet');
    $ownerName = trim((string) ($booking->meta->firstWhere('key', 'owner-name')?->value ?? ''));
    $isMemberOwner = $ownerName === '' && $bookingUser !== null;
    $isCreateMode = (bool) ($isCreate ?? false);
    $closeRoute = $isCreateMode ? route('calendar.index', ['date' => old('date', $reservation?->date)]) : route('admin.bookings.index');
    $formAction = $isCreateMode ? route('admin.bookings.store') : route('admin.bookings.update', $booking);
    $formId = $isCreateMode ? 'admin-booking-create' : 'admin-booking-update';
@endphp

@section('content')
@if($isCreateMode)
<div class="rounded-[8px] border border-[#e8e8e8] bg-white shadow-[0_4px_20px_rgba(0,0,0,0.08)] overflow-hidden" x-data="{ tab: 'booking' }">
    <div class="border-b border-[#ececec] bg-white px-5 pt-3">
        <div class="flex gap-6 text-sm">
            <button type="button" @click="tab = 'booking'" class="border-b-2 px-1 pb-3 font-medium transition-colors" :class="tab === 'booking' ? 'border-[#bf4316] text-[#bf4316]' : 'border-transparent text-[#6a6e73]'">{{ __('booking.admin.bookings.booking_tab') }}</button>
            @can('admin.event')
            <button type="button" @click="tab = 'event'" class="border-b-2 px-1 pb-3 font-medium transition-colors" :class="tab === 'event' ? 'border-[#bf4316] text-[#bf4316]' : 'border-transparent text-[#6a6e73]'">{{ __('booking.admin.bookings.event_tab') }}</button>
            @endcan
        </div>
    </div>

    <div x-show="tab === 'booking'" x-cloak>
        <form method="POST" action="{{ $formAction }}" id="{{ $formId }}">
            @csrf
            @if(request('popup'))
                <input type="hidden" name="popup" value="1">
                <input type="hidden" name="redirect_to" value="{{ route('calendar.index', ['date' => old('date', $reservation?->date ?? now()->format('Y-m-d'))]) }}">
            @endif

            <div class="p-5 space-y-5">
                @if($errors->any())
                    <div class="rounded-[6px] bg-red-50 border border-red-200 px-4 py-3">
                        @foreach($errors->all() as $error)
                            <p class="text-sm text-red-700">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="block text-[13px] font-medium text-[#151515]" for="sid">{{ __('booking.admin.common.court') }}</label>
                        <select name="sid" id="sid" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                            @foreach($squares as $square)
                                <option value="{{ $square->sid }}" @selected(old('sid', $booking->sid) == $square->sid)>{{ $square->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[13px] font-medium text-[#151515]" for="booked_for">{{ __('booking.admin.bookings.booked_for') }}</label>
                        <input type="text" id="booked_for" name="booked_for" value="{{ old('booked_for', $bookedFor) }}" list="admin-player-suggestions" maxlength="120" required placeholder="{{ __('booking.admin.bookings.member_search_placeholder') }}" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none placeholder:text-[#b8b8b8] focus:border-[#151515]">
                    </div>
                </div>

                <div>
                    <div class="grid grid-cols-4 gap-3">
                        <div class="space-y-1">
                            <label class="block text-[13px] font-medium text-[#151515]" for="date">{{ __('booking.admin.bookings.date_start') }}</label>
                            <input type="date" id="date" name="date" value="{{ old('date', $reservation?->date) }}" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[13px] font-medium text-[#151515]" for="time_start">{{ __('booking.admin.bookings.time_start') }}</label>
                            <input type="time" id="time_start" name="time_start" value="{{ old('time_start', $reservation ? substr((string) $reservation->time_start, 0, 5) : '') }}" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[13px] font-medium text-[#151515]" for="admin-booking-date-end">{{ __('booking.admin.bookings.date_end') }}</label>
                            <input type="date" id="admin-booking-date-end" name="date_end" value="{{ old('date_end', $repeatEndDate) }}" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[13px] font-medium text-[#151515]" for="time_end">{{ __('booking.admin.bookings.time_end') }}</label>
                            <input type="time" id="time_end" name="time_end" value="{{ old('time_end', $reservation ? substr((string) $reservation->time_end, 0, 5) : '') }}" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                        </div>
                    </div>
                    <div class="mt-3 space-y-1">
                        <label class="block text-[13px] font-medium text-[#151515]" for="admin-booking-repeat">{{ __('booking.admin.bookings.repeat') }}</label>
                        <select name="repeat_type" id="admin-booking-repeat" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                            @foreach($repeatOptions as $repeatValue => $repeatLabel)
                                <option value="{{ $repeatValue }}" @selected(old('repeat_type', $repeatType) === $repeatValue)>{{ $repeatLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <div class="space-y-3">
                        <div class="space-y-1">
                            <label class="block text-[13px] font-medium text-[#151515]" for="admin-booking-quantity">{{ __('booking.admin.bookings.player_count') }}</label>
                            <select name="quantity" id="admin-booking-quantity" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                                <option value="2" @selected((int) old('quantity', $booking->quantity) === 2)>{{ __('booking.modal.single_option') }}</option>
                                <option value="4" @selected((int) old('quantity', $booking->quantity) === 4)>{{ __('booking.modal.double_option') }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1"
                             x-data="{
                                 quantity: '{{ (int) old('quantity', $booking->quantity) }}',
                                 acResults: [],
                                 acOpen: false,
                                 async fetchAc(v) {
                                     if (this.quantity !== '2' || v.length < 2) { this.acResults = []; this.acOpen = false; return; }
                                     const r = await fetch('/bookings/players?q=' + encodeURIComponent(v));
                                     this.acResults = await r.json();
                                     this.acOpen = this.acResults.length > 0;
                                 }
                             }"
                             @change.document="if($event.target.id==='admin-booking-quantity') quantity=$event.target.value"
                             @click.outside="acOpen=false">
                            <label class="text-[13px] font-medium text-[#6a6e73]" for="admin-mitspieler">{{ __('booking.admin.bookings.teammates') }}</label>
                            <input type="text" id="admin-mitspieler" name="mitspieler" value="{{ old('mitspieler', $playerNames[2]) }}"
                                   maxlength="255"
                                   :placeholder="quantity==='2' ? '{{ __('booking.modal.player_search_placeholder') }}' : '{{ __('booking.admin.bookings.teammates_examples') }}'"
                                   autocomplete="off"
                                   @input.debounce.300ms="fetchAc($event.target.value)"
                                   @focus="fetchAc($event.target.value)"
                                   class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none placeholder:text-[#b8b8b8] focus:border-[#151515]">
                            <ul x-show="acOpen" class="mt-1 w-full overflow-hidden rounded border border-[#e0dbd4] bg-white shadow-md">
                                <template x-for="r in acResults" :key="r">
                                    <li @mousedown.prevent="$el.closest('div').querySelector('#admin-mitspieler').value=r; acResults=[]; acOpen=false" x-text="r" class="cursor-pointer px-3 py-2 text-sm hover:bg-[#f7f5f2]"></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="block text-[13px] font-medium text-[#151515]" for="admin_note">{{ __('booking.admin.bookings.admin_note') }}</label>
                    <textarea id="admin_note" name="admin_note" rows="4" class="w-full rounded-[6px] border border-[#c7c7c7] bg-white px-3 py-2 text-sm text-[#151515] outline-none focus:border-[#151515]">{{ old('admin_note', $adminNote) }}</textarea>
                    <p class="text-xs text-[#6a6e73]">{{ __('booking.admin.bookings.note_hint') }}</p>
                </div>

                <datalist id="admin-player-suggestions">
                    @foreach($users as $user)
                        <option value="{{ $user->alias }}"></option>
                    @endforeach
                </datalist>
            </div>

            <div class="flex items-center gap-3 border-t border-[#ececec] bg-[#fcfcfc] px-5 py-4">
                <button type="submit" class="inline-flex h-10 items-center rounded-[6px] bg-[#bf4316] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#9e3412]">{{ __('booking.admin.bookings.save_action') }}</button>
                <a href="{{ $closeRoute }}" class="inline-flex h-10 items-center rounded-[6px] border border-[#d4d4d4] bg-white px-5 text-sm text-[#151515] transition-colors hover:bg-[#f7f7f7]">{{ __('booking.admin.common.abort') }}</a>
            </div>
        </form>
    </div>

    @can('admin.event')
    <div x-show="tab === 'event'" x-cloak>
        <form method="POST" action="{{ route('admin.events.store') }}" id="admin-event-create">
            @csrf
            @if(request('popup'))
                <input type="hidden" name="popup" value="1">
                <input type="hidden" name="redirect_to" value="{{ route('calendar.index') }}">
            @endif

            <div class="p-5 space-y-5">
                @include('admin.events._form', array_merge($eventFormData ?? [], ['squares' => $squares, 'popup_mode' => true]))
            </div>

            <div class="flex items-center gap-3 border-t border-[#ececec] bg-[#fcfcfc] px-5 py-4">
                <button type="submit" class="inline-flex h-10 items-center rounded-[6px] bg-[#bf4316] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#9e3412]">{{ __('booking.admin.bookings.create_action') }}</button>
                <a href="{{ $closeRoute }}" class="inline-flex h-10 items-center rounded-[6px] border border-[#d4d4d4] bg-white px-5 text-sm text-[#151515] transition-colors hover:bg-[#f7f7f7]">{{ __('booking.admin.common.abort') }}</a>
            </div>
        </form>
    </div>
    @endcan
</div>
@else
<div class="rounded-[8px] border border-[#e8e8e8] bg-white shadow-[0_4px_20px_rgba(0,0,0,0.08)] overflow-hidden">
    <form method="POST" action="{{ $formAction }}" id="{{ $formId }}">
        @csrf
        @method('PUT')
        @if(request('popup'))
            <input type="hidden" name="popup" value="1">
            <input type="hidden" name="redirect_to" value="{{ route('calendar.index', ['date' => old('date', $reservation?->date ?? now()->format('Y-m-d'))]) }}">
        @endif

        <div class="p-6 space-y-6">
            @if($errors->any())
                <div class="rounded-[6px] bg-red-50 border border-red-200 px-4 py-3">
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-700">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div>
                <div class="grid grid-cols-[minmax(0,1fr)_174px] gap-3 items-end">
                    <div class="space-y-1">
                        <label class="block text-[13px] font-medium text-[#151515]" for="booked_for">{{ __('booking.admin.bookings.booked_for') }}</label>
                        <input type="text" id="booked_for" name="booked_for" value="{{ old('booked_for', $bookedFor) }}" list="admin-player-suggestions" maxlength="120" required class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                    </div>
                    @if($isMemberOwner && Route::has('admin.users.edit') && auth()->user()->can('admin.user'))
                        <a href="{{ route('admin.users.edit', $bookingUser) }}" class="inline-flex h-9 items-center justify-center rounded-[6px] border border-[#bf4316] bg-white px-4 text-sm font-medium text-[#bf4316] transition-colors hover:bg-[#fff5f1]">{{ __('booking.admin.bookings.edit_user') }}</a>
                    @else
                        <div></div>
                    @endif
                </div>
            </div>

            <div>
                <div class="grid grid-cols-3 gap-3">
                    <div class="space-y-1">
                        <label class="block text-[13px] font-medium text-[#151515]" for="sid">{{ __('booking.admin.common.court') }}</label>
                        <select name="sid" id="sid" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                            @foreach($squares as $square)
                                <option value="{{ $square->sid }}" @selected(old('sid', $booking->sid) == $square->sid)>{{ $square->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[13px] font-medium text-[#151515]" for="admin-booking-quantity">{{ __('booking.admin.bookings.player_count') }}</label>
                        <select name="quantity" id="admin-booking-quantity" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                            <option value="2" @selected((int) old('quantity', $booking->quantity) === 2)>2</option>
                            <option value="4" @selected((int) old('quantity', $booking->quantity) === 4)>4</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[13px] font-medium text-[#151515]" for="status">{{ __('booking.admin.bookings.booking_status') }}</label>
                        <select name="status" id="status" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                            <option value="single" @selected(old('status', $booking->status) === 'single')>{{ __('booking.admin.bookings.status_active') }}</option>
                            <option value="subscription" @selected(old('status', $booking->status) === 'subscription')>{{ __('booking.admin.bookings.status_series') }}</option>
                            <option value="cancelled" @selected(old('status', $booking->status) === 'cancelled')>{{ __('booking.admin.bookings.status_cancelled') }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div>
                <div class="grid grid-cols-4 gap-3">
                    <div class="space-y-1">
                        <label class="block text-[13px] font-medium text-[#151515]" for="date">{{ __('booking.admin.bookings.date_start') }}</label>
                        <input type="date" id="date" name="date" value="{{ old('date', $reservation?->date) }}" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[13px] font-medium text-[#151515]" for="time_start">{{ __('booking.admin.bookings.time_start') }}</label>
                        <input type="time" id="time_start" name="time_start" value="{{ old('time_start', $reservation ? substr((string) $reservation->time_start, 0, 5) : '') }}" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[13px] font-medium text-[#151515]" for="admin-booking-date-end">{{ __('booking.admin.bookings.date_end') }}</label>
                        <input type="date" id="admin-booking-date-end" name="date_end" value="{{ old('date_end', $repeatEndDate) }}" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[13px] font-medium text-[#151515]" for="time_end">{{ __('booking.admin.bookings.time_end') }}</label>
                        <input type="time" id="time_end" name="time_end" value="{{ old('time_end', $reservation ? substr((string) $reservation->time_end, 0, 5) : '') }}" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                    </div>
                </div>
                <div class="mt-3 space-y-1">
                    <label class="block text-[13px] font-medium text-[#151515]" for="admin-booking-repeat">{{ __('booking.admin.bookings.repeat') }}</label>
                    <select name="repeat_type" id="admin-booking-repeat" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                        @foreach($repeatOptions as $repeatValue => $repeatLabel)
                            <option value="{{ $repeatValue }}" @selected(old('repeat_type', $repeatType) === $repeatValue)>{{ $repeatLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <p class="mb-4 border-b border-[#ebebeb] pb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#6a6e73]">{{ __('booking.admin.bookings.player_names_section') }}</p>
                <div class="space-y-3">
                    <div class="grid grid-cols-[26px_1fr] items-center gap-3">
                        <label class="text-[13px] font-medium text-[#6a6e73] text-right" for="admin-player2">2.</label>
                        <input type="text" id="admin-player2" name="player_name_2" value="{{ old('player_name_2', $playerNames[2]) }}" list="admin-player-suggestions" maxlength="120" required class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                    </div>
                    <div id="admin-player3-field" class="grid grid-cols-[26px_1fr] items-center gap-3">
                        <label class="text-[13px] font-medium text-[#6a6e73] text-right" for="admin-player3">3.</label>
                        <input type="text" id="admin-player3" name="player_name_3" value="{{ old('player_name_3', $playerNames[3]) }}" list="admin-player-suggestions" maxlength="120" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                    </div>
                    <div id="admin-player4-field" class="grid grid-cols-[26px_1fr] items-center gap-3">
                        <label class="text-[13px] font-medium text-[#6a6e73] text-right" for="admin-player4">4.</label>
                        <input type="text" id="admin-player4" name="player_name_4" value="{{ old('player_name_4', $playerNames[4]) }}" list="admin-player-suggestions" maxlength="120" class="w-full h-9 rounded-[6px] border border-[#c7c7c7] bg-white px-3 text-sm text-[#151515] outline-none focus:border-[#151515]">
                    </div>
                </div>
                <datalist id="admin-player-suggestions">
                    @foreach($users as $user)
                        <option value="{{ $user->alias }}"></option>
                    @endforeach
                </datalist>
            </div>

            <div>
                <p class="mb-4 border-b border-[#ebebeb] pb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#6a6e73]">{{ __('booking.admin.bookings.notes_heading') }}</p>
                <div class="space-y-1">
                    <textarea id="admin_note" name="admin_note" rows="5" class="w-full rounded-[6px] border border-[#c7c7c7] bg-white px-3 py-2 text-sm text-[#151515] outline-none focus:border-[#151515]">{{ old('admin_note', $adminNote) }}</textarea>
                    <div class="flex items-center justify-between gap-4 pt-1 text-xs text-[#6a6e73]">
                        <span>{{ __('booking.admin.bookings.note_hint') }}</span>
                        <span>{{ __('booking.admin.bookings.booking_created_at', ['date' => $createdLabel]) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-4 border-t border-[#ececec] bg-[#fcfcfc] px-6 py-4">
            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex h-10 items-center rounded-[6px] bg-[#bf4316] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#9e3412]">{{ __('booking.admin.bookings.save_action') }}</button>
                <a href="{{ $closeRoute }}" class="inline-flex h-10 items-center rounded-[6px] border border-[#d4d4d4] bg-white px-5 text-sm text-[#151515] transition-colors hover:bg-[#f7f7f7]">{{ __('booking.admin.common.abort') }}</a>
            </div>
            <div class="flex items-center gap-3">
                @if($booking->status !== 'cancelled')
                    <button type="submit" form="admin-booking-cancel" class="inline-flex h-10 items-center rounded-[6px] border border-[#d4d4d4] bg-white px-5 text-sm text-[#151515] transition-colors hover:bg-[#f7f7f7]">{{ __('booking.admin.bookings.cancel_booking') }}</button>
                @endif
                <button type="submit" form="admin-booking-delete" class="inline-flex h-10 items-center rounded-[6px] bg-[#c62828] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#a61f1f]">{{ __('booking.admin.common.delete') }}</button>
            </div>
        </div>
    </form>

    @if($booking->status !== 'cancelled')
        <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" id="admin-booking-cancel" class="hidden" onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_cancel')) }})">
            @csrf
            @if(request('popup'))
                <input type="hidden" name="redirect_to" value="{{ route('calendar.index', ['date' => old('date', $reservation?->date ?? now()->format('Y-m-d'))]) }}">
            @endif
        </form>
    @endif

    <form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}" id="admin-booking-delete" class="hidden" onsubmit="return confirm({{ Js::from(__('booking.admin.bookings.confirm_delete')) }})">
        @csrf
        @method('DELETE')
        @if(request('popup'))
            <input type="hidden" name="redirect_to" value="{{ route('calendar.index', ['date' => old('date', $reservation?->date ?? now()->format('Y-m-d'))]) }}">
        @endif
    </form>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    var quantity = document.getElementById('admin-booking-quantity');
    var repeat = document.getElementById('admin-booking-repeat');
    var dateEnd = document.getElementById('admin-booking-date-end');
    var dateStart = document.querySelector('input[name="date"]');
    var player3Field = document.getElementById('admin-player3-field');
    var player4Field = document.getElementById('admin-player4-field');
    var player3Input = document.getElementById('admin-player3');
    var player4Input = document.getElementById('admin-player4');

    function syncAdminBookingFields() {
        if (quantity) {
            var isDouble = quantity.value === '4';
            if (player3Field) { player3Field.style.display = isDouble ? '' : 'none'; }
            if (player4Field) { player4Field.style.display = isDouble ? '' : 'none'; }
            if (player3Input) { player3Input.required = isDouble; if (!isDouble) { player3Input.value = ''; } }
            if (player4Input) { player4Input.required = isDouble; if (!isDouble) { player4Input.value = ''; } }
        }

        var timeStart = document.getElementById('time_start');
        var timeEnd = document.getElementById('time_end');

        if (repeat && dateEnd) {
            var isOnce = repeat.value === 'once';
            var dateFields = [dateStart, timeStart, timeEnd];
            dateFields.forEach(function(f) {
                if (!f) { return; }
                f.readOnly = isOnce;
                f.classList.toggle('is-disabled', isOnce);
            });
            dateEnd.disabled = isOnce;
            dateEnd.classList.toggle('is-disabled', isOnce);
            if (isOnce && dateStart) { dateEnd.value = dateStart.value; }
        }
    }

    if (quantity) { quantity.addEventListener('change', syncAdminBookingFields); }
    if (repeat) { repeat.addEventListener('change', syncAdminBookingFields); }
    if (dateStart) { dateStart.addEventListener('change', syncAdminBookingFields); }
    syncAdminBookingFields();
});
</script>
@endsection

@section('admin-content')
    @yield('content')
@endsection
