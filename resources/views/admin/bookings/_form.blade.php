<div class="flex flex-col gap-6">

    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.common.booking') }}</h2>
        </div>
        <div class="px-6 py-5 flex flex-col gap-4">

            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.common.member') }}</label>
                <select name="uid" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    @foreach($users as $user)
                        <option value="{{ $user->uid }}" @selected(old('uid', $booking->uid) == $user->uid)>{{ $user->alias }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.common.court') }}</label>
                <select name="sid" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    @foreach($squares as $square)
                        <option value="{{ $square->sid }}" @selected(old('sid', $booking->sid) == $square->sid)>{{ $square->name }} {{ $square->display_name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.common.date') }}</label>
                    <input type="date" name="date" value="{{ old('date', $reservation?->date) }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.bookings.player_count') }}</label>
                    <select name="quantity" id="admin-booking-quantity" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        <option value="2" @selected((int) old('quantity', $booking->quantity) === 2)>{{ __('booking.admin.bookings.single') }}</option>
                        <option value="4" @selected((int) old('quantity', $booking->quantity) === 4)>{{ __('booking.admin.bookings.double') }}</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.common.from') }}</label>
                    <input type="time" name="time_start" value="{{ old('time_start', $reservation ? substr((string) $reservation->time_start, 0, 5) : '') }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.common.to') }}</label>
                    <input type="time" name="time_end" value="{{ old('time_end', $reservation ? substr((string) $reservation->time_end, 0, 5) : '') }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.common.status') }}</label>
                <select name="status" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    <option value="single" @selected(old('status', $booking->status) === 'single')>{{ __('booking.admin.bookings.status_active') }}</option>
                    <option value="cancelled" @selected(old('status', $booking->status) === 'cancelled')>{{ __('booking.admin.bookings.status_cancelled') }}</option>
                </select>
            </div>

        </div>
    </div>

    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.bookings.player_names') }}</h2>
        </div>
        <div class="px-6 py-5 flex flex-col gap-4">

            <div class="flex flex-col gap-1"
                 x-data="{
                     quantity: '{{ (int) old('quantity', $booking->quantity) }}',
                     acResults: [],
                     acOpen: false,
                     async fetchAc(v) {
                         if (v.length < 2) { this.acResults = []; this.acOpen = false; return; }
                         const r = await fetch('/bookings/players?q=' + encodeURIComponent(v) + '&quantity=' + encodeURIComponent(this.quantity));
                         this.acResults = await r.json();
                         this.acOpen = this.acResults.length > 0;
                     }
                 }"
                 @change.document="if($event.target.id==='admin-booking-quantity') quantity=$event.target.value"
                 @click.outside="acOpen=false">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="admin-mitspieler">{{ __('booking.admin.bookings.teammates') }}</label>
                <input type="text" id="admin-mitspieler" name="mitspieler" value="{{ old('mitspieler', $playerNames[2]) }}" maxlength="255" :placeholder="quantity==='2' ? '{{ __('booking.modal.player_search_placeholder') }}' : '{{ __('booking.admin.bookings.teammates_examples') }}'" autocomplete="off" @input.debounce.300ms="fetchAc($event.target.value)" @focus="fetchAc($event.target.value)" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                <ul x-show="acOpen" class="mt-1 w-full overflow-hidden rounded border border-[#e0dbd4] bg-white shadow-md">
                    <template x-for="r in acResults" :key="r">
                        <li @mousedown.prevent="$el.closest('div').querySelector('#admin-mitspieler').value=r; acResults=[]; acOpen=false" x-text="r" class="cursor-pointer px-3 py-2 text-sm hover:bg-[#f7f5f2]"></li>
                    </template>
                </ul>
            </div>

        </div>
    </div>

</div>
