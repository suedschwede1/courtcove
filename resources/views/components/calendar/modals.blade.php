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
        error: null,
        loading: false,
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
            this.error = null;
            this.open = true;
        },
        async submitBooking(form) {
            this.loading = true;
            this.error = null;
            try {
                const data = new FormData(form);
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: data,
                });
                const json = await res.json();
                if (res.ok && json.redirect) {
                    window.location.href = json.redirect;
                } else {
                    this.error = json.error ?? '{{ __('booking.messages.booking_failed') }}';
                }
            } catch {
                this.error = '{{ __('booking.messages.booking_failed') }}';
            } finally {
                this.loading = false;
            }
        }
     }"
     @open-booking.window="openBooking($event.detail)"
     x-show="open"
     x-transition.opacity
     @keydown.escape.window="open = false"
     @click.self="open = false"
     class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
     style="display: none;">
    <div class="relative w-full max-w-[580px] overflow-hidden rounded-[8px] border border-[#e8e8e8] bg-white shadow-[0_4px_20px_rgba(0,0,0,0.08)]">
        <div class="border-b border-[#ebebeb] bg-white px-6 pt-3 pb-0">
            <div class="flex items-start justify-between gap-4">
                <p class="mb-3 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#6a6e73]">Neue Buchung</p>
                <button type="button"
                        @click="open = false"
                        class="text-[20px] leading-none text-[#8a8d90] transition-colors hover:text-[#151515]">×</button>
            </div>
            <div class="flex gap-6 border-b border-[#ebebeb]">
                <span class="border-b-2 border-[#bf4316] px-0 pb-3 text-sm font-semibold text-[#bf4316]">Buchung</span>
            </div>
        </div>

        <form method="POST" action="{{ route('bookings.store') }}" @submit.prevent="submitBooking($el)">
            @csrf
            <input type="hidden" name="sid" x-bind:value="sid">
            
            <input type="hidden" name="time_start" x-bind:value="timeStart">
            <input type="hidden" name="time_end" x-bind:value="timeEnd">

            <div class="px-6 py-5">
                <div class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="ui-field">
                            <label class="ui-label text-[#151515]">Platz</label>
                            <input type="text" x-bind:value="squareName" readonly class="ui-input bg-[#fafafa] text-[#151515]">
                        </div>
                        <div class="ui-field">
                            <label class="ui-label text-[#151515]">Gebucht für</label>
                            <input type="text" value="{{ auth()->user()->name }}" readonly class="ui-input bg-[#fafafa] text-[#151515]">
                        </div>
                    </div>

                    <div class="ui-form-divider">
                        <div class="ui-section-label">Zeitraum</div>
                        <div class="grid grid-cols-4 gap-3">
                            <div class="ui-field">
                                <label class="ui-label text-[#151515]">Datum (Beginn)</label>
                                <input type="date" name="date" x-model="date" class="ui-input ui-date-input">
                            </div>
                            <div class="ui-field">
                                <label class="ui-label text-[#151515]">Zeit (Beginn)</label>
                                <input type="text" x-bind:value="timeStartFormatted" readonly class="ui-input bg-[#fafafa] text-[#151515]">
                            </div>
                            <div class="ui-field">
                                <label class="ui-label text-[#151515]">Datum (Ende)</label>
                                <input type="date" x-model="date" class="ui-input ui-date-input">
                            </div>
                            <div class="ui-field">
                                <label class="ui-label text-[#151515]">Zeit (Ende)</label>
                                <input type="text" x-bind:value="timeEndFormatted" readonly class="ui-input bg-[#fafafa] text-[#151515]">
                            </div>
                        </div>
                    </div>

                    <div class="ui-form-divider">
                        <div class="ui-section-label">Spieler</div>
                        <div class="ui-field">
                            <label class="ui-label text-[#151515]">Spieleranzahl</label>
                            <select name="quantity" x-model="quantity" class="ui-select">
                                <option value="2">2 (Einzel)</option>
                                <option value="4">4 (Doppel)</option>
                            </select>
                        </div>

                        <div class="ui-field">
                            <label class="ui-label text-[#151515]">2. Spielername</label>
                            <input type="text"
                                   name="player_name_2"
                                   required
                                   placeholder="Mitspielername suchen ..."
                                   class="ui-input placeholder:text-[#b8b8b8]">
                        </div>

                        <div class="ui-field" x-show="quantity == '4'">
                            <label class="ui-label text-[#151515]">3. Spielername</label>
                            <input type="text"
                                   name="player_name_3"
                                   placeholder="Mitspielername suchen ..."
                                   class="ui-input placeholder:text-[#b8b8b8]">
                        </div>

                        <div class="ui-field" x-show="quantity == '4'">
                            <label class="ui-label text-[#151515]">4. Spielername</label>
                            <input type="text"
                                   name="player_name_4"
                                   placeholder="Mitspielername suchen ..."
                                   class="ui-input placeholder:text-[#b8b8b8]">
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-[#ebebeb] bg-[#fafafa] px-6 py-4">
                <template x-if="error">
                    <p class="mb-3 text-sm text-red-600" x-text="error"></p>
                </template>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="ui-btn ui-btn-primary px-[19px]" :disabled="loading" x-text="loading ? 'Speichern …' : 'Speichern'"></button>
                    <button type="button" @click="open = false" class="ui-btn ui-btn-ghost border border-[#d1cbc0] bg-white px-[19px] text-[#151515] hover:bg-[#f7f7f7]">Abbrechen</button>
                </div>
            </div>
        </form>
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
        dateStartVal: '',
        timeStartVal: '',
        dateEndVal: '',
        timeEndVal: '',
        openEvent(detail) {
            this.sid                = String(detail.sid);
            this.date               = detail.date;
            this.timeStart          = detail.timeStart;
            this.timeEnd            = detail.timeEnd;
            this.timeStartFormatted = detail.timeStartFormatted;
            this.timeEndFormatted   = detail.timeEndFormatted;
            this.squareName         = detail.squareName;
            this.dateLabel          = detail.dateLabel;
            this.timeLabel          = detail.timeLabel;
            this.dateStartVal = detail.date;
            this.dateEndVal   = detail.date;
            this.timeStartVal = detail.timeStartFormatted;
            this.timeEndVal   = detail.timeEndFormatted;
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
                               x-model="dateStartVal"
                               required
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Zeit Start</label>
                        <input type="time"
                               name="time_start"
                               x-model="timeStartVal"
                               required
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Datum Ende</label>
                        <input type="date"
                               name="date_end"
                               x-model="dateEndVal"
                               required
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Zeit Ende</label>
                        <input type="time"
                               name="time_end"
                               x-model="timeEndVal"
                               required
                               class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                </div>

                {{-- Platz --}}
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">Platz</label>
                    <select name="sid"
                            x-model="sid"
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



