@props(['date', 'squares'])

@auth
<div x-data="{
        open: false,
        bid: null,
        squareName: '',
        dateLabel: '',
        timeLabel: '',
        quantity: '2',
        mitspieler: '',
        openCancel(detail) {
            this.bid        = detail.bid;
            this.squareName = detail.squareName;
            this.dateLabel  = detail.dateLabel;
            this.timeLabel  = detail.timeLabel;
            this.quantity   = detail.quantity   || '2';
            this.mitspieler = detail.mitspieler || '';
            this.open = true;
        }
     }"
     @open-cancel.window="openCancel($event.detail)">
    <x-ui.modal id="cancel-dialog" show="open" close="open = false">
        <x-slot:title>
            {{ __('booking.modal.cancel_heading') }}
        </x-slot:title>

        <x-ui.summary-tiles :items="[
            ['label' => __('booking.calendar.date'), 'bind' => 'dateLabel'],
            ['label' => __('booking.calendar.time'), 'bind' => 'timeLabel'],
            ['label' => __('booking.calendar.court'), 'bind' => 'squareName'],
        ]" />

        <p class="ds-modal__message">{{ __('booking.modal.cancel_message') }}</p>

        <div class="ds-modal__actions">
            <x-ui.button variant="secondary" data-modal-initial x-on:click="open = false; $dispatch('open-edit-booking', {bid, squareName, dateLabel, timeLabel, quantity, mitspieler})">{{ __('booking.modal.edit_booking') }}</x-ui.button>
            <form method="POST" x-bind:action="@js(url('/bookings')) + '/' + bid">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="danger">{{ __('booking.modal.cancel_booking') }}</x-ui.button>
            </form>
            <x-ui.button variant="secondary" class="ds-modal__dismiss" x-on:click="open = false">{{ __('booking.modal.cancel') }}</x-ui.button>
        </div>
    </x-ui.modal>
</div>
@endauth

@auth
<div x-data="{
        open: false,
        bid: null,
        squareName: '',
        dateLabel: '',
        timeLabel: '',
        quantity: '2',
        mitspieler: '',
        acResults: [],
        acOpen: false,
        error: null,
        loading: false,
        get placeholder() {
            return this.quantity === '2' ? @js(__('booking.modal.player_search_placeholder')) : @js(__('booking.admin.bookings.teammates_examples'));
        },
        async fetchAc(v) {
            if (v.length < 2) { this.acResults = []; this.acOpen = false; return; }
            const r = await fetch(@js(route('bookings.players')) + '?q=' + encodeURIComponent(v) + '&quantity=' + encodeURIComponent(this.quantity));
            this.acResults = await r.json();
            this.acOpen = this.acResults.length > 0;
        },
        openEdit(detail) {
            this.bid        = detail.bid;
            this.squareName = detail.squareName;
            this.dateLabel  = detail.dateLabel;
            this.timeLabel  = detail.timeLabel;
            this.quantity   = String(detail.quantity ?? 2);
            this.mitspieler = detail.mitspieler ?? '';
            this.acResults  = [];
            this.acOpen     = false;
            this.error = null;
            this.loading = false;
            this.open = true;
        },
        async submitEdit(form) {
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
                    this.error = json.error ?? @js(__('booking.messages.booking_failed'));
                }
            } catch {
                this.error = @js(__('booking.messages.booking_failed'));
            } finally {
                this.loading = false;
            }
        }
     }"
     @open-edit-booking.window="openEdit($event.detail)">
    <x-ui.modal id="edit-dialog" show="open" close="open = false" :eyebrow="__('booking.modal.edit_booking_heading')">
        <x-slot:title>
            <span x-text="squareName"></span>
        </x-slot:title>

        <x-ui.summary-tiles :items="[
            ['label' => __('booking.calendar.date'), 'bind' => 'dateLabel'],
            ['label' => __('booking.calendar.time'), 'bind' => 'timeLabel'],
            ['label' => __('booking.calendar.court'), 'bind' => 'squareName'],
        ]" />

        <form id="edit-form" x-bind:action="@js(url('/bookings')) + '/' + bid" method="POST" @submit.prevent="submitEdit($el)" class="ds-modal__form">
            @csrf
            <input type="hidden" name="_method" value="PUT">

            <x-ui.option-group :legend="__('booking.modal.play_type')">
                <x-ui.option-tile name="quantity" value="2" :label="__('booking.modal.single_tile')" x-model="quantity" />
                <x-ui.option-tile name="quantity" value="4" :label="__('booking.modal.double_tile')" x-model="quantity" />
            </x-ui.option-group>

            <x-ui.field for="edit-mitspieler" :label="__('booking.admin.bookings.teammates')" :help="__('booking.modal.player_help')" x-on:click.outside="acOpen = false">
                <x-ui.input name="mitspieler" required maxlength="255" autocomplete="off" data-modal-initial x-model="mitspieler" x-on:input.debounce.300ms="fetchAc($event.target.value)" x-on:focus="fetchAc(mitspieler)" x-bind:placeholder="placeholder" />
                <ul class="ds-suggest" x-show="acOpen">
                    <template x-for="r in acResults" :key="r">
                        <li><button type="button" class="ds-suggest__item" x-text="r" @mousedown.prevent @click="mitspieler = r; acResults = []; acOpen = false"></button></li>
                    </template>
                </ul>
            </x-ui.field>

            <x-ui.notice tone="problem" x-show="error"><span x-text="error"></span></x-ui.notice>
        </form>

        <x-slot:footer>
            <div class="ds-modal__footer-part">
                <x-ui.button variant="secondary" :block="false" class="ds-modal__dismiss" x-on:click="open = false">{{ __('booking.modal.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="edit-form" :block="false" x-bind:disabled="loading"><span x-text="loading ? @js(__('booking.modal.saving')) : @js(__('booking.admin.bookings.save_action'))"></span></x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.modal>
</div>
@endauth

@auth
@php
    $courtLookup = $squares->mapWithKeys(fn ($s) => [$s->sid => ['alias' => $s->display_name, 'number' => (string) $s->name]])->all();
@endphp
<div x-data="{
        open: false,
        state: 'form',
        redirect: null,
        quote: null,
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
        mitspieler: '',
        acResults: [],
        acOpen: false,
        error: null,
        loading: false,
        courts: @js($courtLookup),
        get courtAlias() {
            const c = this.courts[this.sid];
            return c ? c.alias : this.squareName;
        },
        get courtLabel() {
            const c = this.courts[this.sid];
            return c ? c.alias + ' · ' + c.number : this.squareName;
        },
        get timeRange() {
            return this.timeStartFormatted + ' – ' + this.timeEndFormatted;
        },
        get placeholder() {
            return this.quantity === '2' ? @js(__('booking.modal.player_search_placeholder')) : @js(__('booking.admin.bookings.teammates_examples'));
        },
        close() {
            if (this.state === 'success' && this.redirect) {
                window.location.href = this.redirect;
            } else {
                this.open = false;
            }
        },
        async fetchAc(v) {
            if (v.length < 2) { this.acResults = []; this.acOpen = false; return; }
            const r = await fetch(@js(route('bookings.players')) + '?q=' + encodeURIComponent(v) + '&quantity=' + encodeURIComponent(this.quantity));
            this.acResults = await r.json();
            this.acOpen = this.acResults.length > 0;
        },
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
            this.state = 'form';
            this.quantity = '2';
            this.mitspieler = '';
            this.acResults = [];
            this.acOpen = false;
            this.error = null;
            this.loading = false;
            this.redirect = null;
            this.quote = null;
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
                    this.redirect = json.redirect;
                    this.quote = json.quote ?? null;
                    this.state = 'success';
                    this.$nextTick(() => document.querySelector('#booking-dialog [data-modal-initial]')?.focus());
                } else {
                    this.error = json.error ?? @js(__('booking.messages.booking_failed'));
                }
            } catch {
                this.error = @js(__('booking.messages.booking_failed'));
            } finally {
                this.loading = false;
            }
        }
     }"
     @open-booking.window="openBooking($event.detail)">
    <x-ui.modal id="booking-dialog" show="open" close="close()" :eyebrow="__('booking.modal.new_booking')" x-bind:data-state="state">
        <x-slot:title>
            <span x-text="state === 'success' ? @js(__('booking.modal.success_title')) : @js(__('booking.calendar.book_court', ['court' => '__COURT__'])).replace('__COURT__', courtAlias)"></span>
        </x-slot:title>

        <div x-show="state === 'form'">
            <x-ui.summary-tiles :items="[
                ['label' => __('booking.calendar.date'), 'bind' => 'dateLabel'],
                ['label' => __('booking.calendar.time'), 'bind' => 'timeRange'],
                ['label' => __('booking.calendar.court'), 'bind' => 'courtLabel'],
            ]" />
        </div>

        <form id="booking-form" method="POST" action="{{ route('bookings.store') }}" @submit.prevent="submitBooking($el)" class="ds-modal__form" x-show="state === 'form'">
            @csrf
            <input type="hidden" name="sid" x-bind:value="sid">
            <input type="hidden" name="date" x-bind:value="date">
            <input type="hidden" name="time_start" x-bind:value="timeStart">
            <input type="hidden" name="time_end" x-bind:value="timeEnd">

            <x-ui.option-group :legend="__('booking.modal.play_type')">
                <x-ui.option-tile name="quantity" value="2" :label="__('booking.modal.single_tile')" x-model="quantity" />
                <x-ui.option-tile name="quantity" value="4" :label="__('booking.modal.double_tile')" x-model="quantity" />
            </x-ui.option-group>

            <x-ui.field for="booking-mitspieler" :label="__('booking.admin.bookings.teammates')" :help="__('booking.modal.player_help')" x-on:click.outside="acOpen = false">
                <x-ui.input name="mitspieler" required maxlength="255" autocomplete="off" x-model="mitspieler" x-on:input.debounce.300ms="fetchAc($event.target.value)" x-on:focus="fetchAc(mitspieler)" x-bind:placeholder="placeholder" />
                <ul class="ds-suggest" x-show="acOpen">
                    <template x-for="r in acResults" :key="r">
                        <li><button type="button" class="ds-suggest__item" x-text="r" @mousedown.prevent @click="mitspieler = r; acResults = []; acOpen = false"></button></li>
                    </template>
                </ul>
            </x-ui.field>

            <x-ui.notice tone="problem" x-show="error"><span x-text="error"></span></x-ui.notice>
        </form>

        <div class="ds-success" role="status" x-show="state === 'success'">
            <span class="ds-success__icon"><x-ui.icon name="check" :size="32" /></span>
            <p class="ds-success__title">{{ __('booking.modal.success_title') }}</p>
            <p class="ds-success__text" x-text="dateLabel + ' · ' + timeRange + ' · ' + courtLabel"></p>
            <p class="ds-modal__quote" x-show="quote" x-text="quote"></p>
        </div>

        <x-slot:footer>
            <div class="ds-modal__footer-part" x-show="state === 'form'">
                <x-ui.button variant="secondary" :block="false" class="ds-modal__dismiss" x-on:click="close()">{{ __('booking.modal.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="booking-form" :block="false" x-bind:disabled="loading"><span x-text="loading ? @js(__('booking.modal.saving')) : @js(__('booking.modal.book_binding'))"></span><span class="ds-only-phone" x-text="' · ' + timeRange"></span></x-ui.button>
            </div>
            <div class="ds-modal__footer-part ds-modal__footer-part--stack" x-show="state === 'success'">
                <x-ui.button variant="dark" :href="route('calendar.index')" x-bind:href="redirect" data-modal-initial>{{ __('booking.modal.to_calendar') }}</x-ui.button>
                <x-ui.button variant="text" :href="route('account.bookings')">{{ __('booking.modal.view_my_bookings') }}</x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.modal>
</div>
@endauth

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
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.modal.create_event') }}</h2>
            <p class="text-sm text-[#6a6e73] mt-0.5" x-text="squareName + ' · ' + dateLabel + ' · ' + timeLabel"></p>
        </div>

        <form method="POST" action="{{ route('admin.events.store') }}">
            @csrf
            <input type="hidden" name="status" value="enabled">
            <input type="hidden" name="redirect_to" value="{{ url('/') }}">

            <div class="px-6 py-4 flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.modal.event_name_label') }}</label>
                    <input type="text" name="name" required placeholder="{{ __('booking.modal.event_name_placeholder') }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>

                <div class="booking-mobile-dialog__summary-grid grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.modal.date_start') }}</label>
                        <input type="date" name="date_start" x-model="dateStartVal" required class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.modal.time_start') }}</label>
                        <input type="time" name="time_start" x-model="timeStartVal" required class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.modal.date_end') }}</label>
                        <input type="date" name="date_end" x-model="dateEndVal" required class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.modal.time_end') }}</label>
                        <input type="time" name="time_end" x-model="timeEndVal" required class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.calendar.court') }}</label>
                    <select name="sid" x-model="sid" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        @foreach($squares as $square)
                            <option value="{{ $square->sid }}">{{ $square->display_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.modal.event_description_optional') }}</label>
                    <textarea name="description" rows="2" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent resize-none"></textarea>
                </div>
            </div>

            <div class="px-6 pb-5 flex flex-col gap-2">
                <button type="submit" class="w-full bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium py-2 rounded transition-colors">{{ __('booking.modal.save_event') }}</button>
                <button type="button" @click="open = false" class="w-full border border-[#d1cbc0] text-[#6a6e73] text-sm py-2 rounded hover:bg-[#f9f8f6] transition-colors">{{ __('booking.modal.cancel') }}</button>
            </div>
        </form>

        <button type="button" @click="open = false" class="absolute top-3 right-4 text-[#9a9a9a] hover:text-[#151515] text-lg leading-none">✕</button>
    </div>
</div>
@endcan

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

