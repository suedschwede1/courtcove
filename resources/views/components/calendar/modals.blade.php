@props(['date', 'squares'])

{{-- ═══════════════════════════════════════════════════════
     FEEDBACK MODAL — auto-close nach 4s
     ═══════════════════════════════════════════════════════ --}}
@if(session()->has('success') || session()->has('error'))
<div x-data="{ open: true }"
     x-init="setTimeout(() => open = false, 4000)"
     x-show="open"
     x-transition.opacity
     @keydown.escape.window="open = false"
     @click.self="open = false"
     class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
     style="display: none;">
    <div class="relative w-full max-w-sm bg-white rounded-xl shadow-xl border border-[#e0ddd7] px-6 py-5">
        @if(session('success'))
            <p class="text-sm font-medium text-green-700">{{ session('success') }}</p>
        @else
            <p class="text-sm font-medium text-red-600">{{ session('error') }}</p>
        @endif
        <button type="button"
                @click="open = false"
                class="mt-4 w-full border border-[#d1cbc0] text-[#6a6e73] text-sm py-2 rounded hover:bg-[#f9f8f6] transition-colors">
            Schließen
        </button>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════
     CANCEL MODAL — Buchung stornieren (nur eigene)
     ═══════════════════════════════════════════════════════ --}}
@auth
<div x-data="{
        open: false,
        bid: null,
        editUrl: '',
        squareName: '',
        dateLabel: '',
        timeLabel: '',
        openCancel(detail) {
            this.bid        = detail.bid;
            this.editUrl    = detail.editUrl   || '';
            this.squareName = detail.squareName;
            this.dateLabel  = detail.dateLabel;
            this.timeLabel  = detail.timeLabel;
            this.open = true;
        }
     }"
     @open-cancel.window="openCancel($event.detail)"
     x-show="open"
     x-transition.opacity
     @keydown.escape.window="open = false"
     @click.self="open = false"
     class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
     style="display: none;">
    <div class="relative w-full max-w-md bg-white rounded-xl shadow-xl border border-[#e0ddd7]">

        <div class="px-6 pt-5 pb-3 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]"
                style="font-family: var(--font-display)">Buchung stornieren</h2>
            <p class="text-sm text-[#6a6e73] mt-0.5"
               x-text="squareName + ' · ' + dateLabel + ' · ' + timeLabel"></p>
        </div>

        <div class="px-6 py-4">
            <p class="text-sm text-[#6a6e73]">
                Möchten Sie diese Buchung wirklich stornieren? Diese Aktion kann nicht rückgängig gemacht werden.
            </p>
        </div>

        <div class="px-6 pb-5 flex flex-col gap-2">
            <template x-if="editUrl">
                <a x-bind:href="editUrl"
                   class="w-full text-center text-sm border border-[#d1cbc0] text-[#6a6e73] py-2 rounded hover:bg-[#f9f8f6] transition-colors block">
                    Buchung bearbeiten
                </a>
            </template>
            <form method="POST" x-bind:action="'/bookings/' + bid">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="w-full bg-red-600 hover:bg-red-700 text-white text-sm font-medium py-2 rounded transition-colors">
                    Buchung stornieren
                </button>
            </form>
            <button type="button"
                    @click="open = false"
                    class="w-full border border-[#d1cbc0] text-[#6a6e73] text-sm py-2 rounded hover:bg-[#f9f8f6] transition-colors">
                Abbrechen
            </button>
        </div>

        <button type="button"
                @click="open = false"
                class="absolute top-3 right-4 text-[#9a9a9a] hover:text-[#151515] text-lg leading-none">✕</button>
    </div>
</div>
@endauth

{{-- ═══════════════════════════════════════════════════════
     BOOKING MODAL — Neue Buchung
     ═══════════════════════════════════════════════════════ --}}
@auth
<div x-data="{
        open: false,
        sid: null,
        date: null,
        timeStart: null,
        timeEnd: null,
        squareName: '',
        dateLabel: '',
        timeLabel: '',
        timeStartFormatted: '',
        timeEndFormatted: '',
        quantity: '2',
        openBooking(detail) {
            this.sid                = detail.sid;
            this.date               = detail.date;
            this.timeStart          = detail.timeStart;
            this.timeEnd            = detail.timeEnd;
            this.squareName         = detail.squareName;
            this.dateLabel          = detail.dateLabel;
            this.timeLabel          = detail.timeLabel;
            this.timeStartFormatted = detail.timeStartFormatted;
            this.timeEndFormatted   = detail.timeEndFormatted;
            this.quantity = '2';
            this.open = true;
        }
     }"
     @open-booking.window="openBooking($event.detail)"
     x-show="open"
     x-transition.opacity
     @keydown.escape.window="open = false"
     @click.self="open = false"
     class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
     style="display: none;">
    <div class="relative w-full max-w-md bg-white rounded-xl shadow-xl border border-[#e0ddd7]">

        <div class="px-6 pt-5 pb-3 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]"
                style="font-family: var(--font-display)"
                x-text="squareName"></h2>
            <p class="text-sm text-[#6a6e73] mt-0.5"
               x-text="dateLabel + ' · ' + timeLabel"></p>
        </div>

        <form method="POST" action="{{ route('bookings.store') }}">
            @csrf
            <input type="hidden" name="sid"        x-bind:value="sid">
            <input type="hidden" name="date"       x-bind:value="date">
            <input type="hidden" name="time_start" x-bind:value="timeStart">
            <input type="hidden" name="time_end"   x-bind:value="timeEnd">

            <div class="px-6 py-4 flex flex-col gap-4">

                {{-- Anzahl Spieler --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Anzahl Spieler</label>
                    <select name="quantity"
                            x-model="quantity"
                            class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        <option value="2">Einzel (2 Spieler)</option>
                        <option value="4">Doppel (4 Spieler)</option>
                    </select>
                </div>

                {{-- Spieler 2 — immer sichtbar, required --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Mitspieler</label>
                    <input type="text"
                           name="player2"
                           required
                           placeholder="Name Mitspieler"
                           class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>

                {{-- Spieler 3 — nur Doppel --}}
                <div class="flex flex-col gap-1" x-show="quantity == '4'">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Mitspieler 3</label>
                    <input type="text"
                           name="player3"
                           placeholder="Name Mitspieler 3"
                           class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>

                {{-- Spieler 4 — nur Doppel --}}
                <div class="flex flex-col gap-1" x-show="quantity == '4'">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Mitspieler 4</label>
                    <input type="text"
                           name="player4"
                           placeholder="Name Mitspieler 4"
                           class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>

            </div>

            <div class="px-6 pb-5 flex flex-col gap-2">
                @can('admin.event')
                <button type="button"
                        @click="open = false; $dispatch('open-event', {sid, date, timeStart, timeEnd, timeStartFormatted, timeEndFormatted, squareName, dateLabel, timeLabel})"
                        class="w-full text-center text-sm text-[#bf4316] hover:underline py-1">
                    Veranstaltung anlegen
                </button>
                @endcan
                <button type="submit"
                        class="w-full bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium py-2 rounded transition-colors">
                    Jetzt buchen
                </button>
                <button type="button"
                        @click="open = false"
                        class="w-full border border-[#d1cbc0] text-[#6a6e73] text-sm py-2 rounded hover:bg-[#f9f8f6] transition-colors">
                    Abbrechen
                </button>
            </div>
        </form>

        <button type="button"
                @click="open = false"
                class="absolute top-3 right-4 text-[#9a9a9a] hover:text-[#151515] text-lg leading-none">✕</button>
    </div>
</div>
@endauth

{{-- ═══════════════════════════════════════════════════════
     EVENT MODAL — Veranstaltung anlegen (nur Admin)
     ═══════════════════════════════════════════════════════ --}}
@can('admin.event')
<div x-data="{
        open: false,
        sid: null,
        date: null,
        timeStart: null,
        timeEnd: null,
        timeStartFormatted: '',
        timeEndFormatted: '',
        squareName: '',
        dateLabel: '',
        timeLabel: '',
        openEvent(detail) {
            this.sid                = detail.sid;
            this.date               = detail.date;
            this.timeStart          = detail.timeStart;
            this.timeEnd            = detail.timeEnd;
            this.timeStartFormatted = detail.timeStartFormatted;
            this.timeEndFormatted   = detail.timeEndFormatted;
            this.squareName         = detail.squareName;
            this.dateLabel          = detail.dateLabel;
            this.timeLabel          = detail.timeLabel;
            this.open = true;
        }
     }"
     @open-event.window="openEvent($event.detail)"
     x-show="open"
     x-transition.opacity
     @keydown.escape.window="open = false"
     @click.self="open = false"
     class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
     style="display: none;">
    <div class="relative w-full max-w-lg bg-white rounded-xl shadow-xl border border-[#e0ddd7]">

        <div class="px-6 pt-5 pb-3 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]"
                style="font-family: var(--font-display)">Veranstaltung anlegen</h2>
            <p class="text-sm text-[#6a6e73] mt-0.5"
               x-text="squareName + ' · ' + dateLabel + ' · ' + timeLabel"></p>
        </div>

        <form method="POST" action="{{ route('admin.events.store') }}">
            @csrf
            <input type="hidden" name="status"      value="enabled">
            <input type="hidden" name="redirect_to" value="{{ url('/') }}">

            <div class="px-6 py-4 flex flex-col gap-4">

                {{-- Veranstaltungsname --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Veranstaltungsname</label>
                    <input type="text"
                           name="name"
                           required
                           placeholder="z.B. Vereinsturnier"
                           class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>

                {{-- Datum/Zeit Start + Ende (2-Spalten-Grid) --}}
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Datum Start</label>
                        <input type="date"
                               name="date_start"
                               x-bind:value="date"
                               required
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Zeit Start</label>
                        <input type="time"
                               name="time_start"
                               x-bind:value="timeStartFormatted"
                               required
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Datum Ende</label>
                        <input type="date"
                               name="date_end"
                               x-bind:value="date"
                               required
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Zeit Ende</label>
                        <input type="time"
                               name="time_end"
                               x-bind:value="timeEndFormatted"
                               required
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                </div>

                {{-- Platz --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Platz</label>
                    <select name="sid"
                            x-bind:value="sid"
                            class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        @foreach($squares as $square)
                            <option value="{{ $square->sid }}">{{ $square->display_name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Beschreibung (optional) --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Beschreibung (optional)</label>
                    <textarea name="description"
                              rows="2"
                              class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent resize-none"></textarea>
                </div>

            </div>

            <div class="px-6 pb-5 flex flex-col gap-2">
                <button type="submit"
                        class="w-full bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium py-2 rounded transition-colors">
                    Veranstaltung speichern
                </button>
                <button type="button"
                        @click="open = false"
                        class="w-full border border-[#d1cbc0] text-[#6a6e73] text-sm py-2 rounded hover:bg-[#f9f8f6] transition-colors">
                    Abbrechen
                </button>
            </div>
        </form>

        <button type="button"
                @click="open = false"
                class="absolute top-3 right-4 text-[#9a9a9a] hover:text-[#151515] text-lg leading-none">✕</button>
    </div>
</div>
@endcan

{{-- ═══════════════════════════════════════════════════════
     ADMIN IFRAME MODAL — bleibt für booking.js
     ═══════════════════════════════════════════════════════ --}}
@auth
<div id="admin-booking-modal" class="booking-modal booking-modal--iframe" style="display:none;">
    <div class="booking-modal__viewport booking-modal--iframe">
        <div class="booking-modal__card booking-modal__card--iframe">
            <button id="abm-close" type="button" class="booking-modal__close booking-modal__close--iframe">✕</button>
            <iframe id="abm-iframe" src="" class="booking-modal__iframe"></iframe>
        </div>
    </div>
</div>
@endauth
