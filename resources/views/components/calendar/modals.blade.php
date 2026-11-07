@props(['date', 'squares'])

@auth
<div x-data="{
        open: false,
        state: 'form',
        loading: false,
        changed: false,
        closeAfterRequest: false,
        error: null,
        bid: null,
        squareName: '',
        dateLabel: '',
        timeLabel: '',
        quantity: '2',
        mitspieler: '',
        searchOpen: false,
        searchRemaining: 0,
        get hintText() {
            const text = this.searchRemaining === 1 ? @js(trans_choice('booking.calendar.end.hint', 1, ['count' => '__N__'])) : @js(trans_choice('booking.calendar.end.hint', 2, ['count' => '__N__']));
            return text.replace('__N__', this.searchRemaining);
        },
        openCancel(detail) {
            // A request from a dialog closed mid-way is still running; its reload follows.
            if (this.loading) return;
            this.bid        = detail.bid;
            this.squareName = detail.squareName;
            this.dateLabel  = detail.dateLabel;
            this.timeLabel  = detail.timeLabel;
            this.quantity   = detail.quantity   || '2';
            this.mitspieler = detail.mitspieler || '';
            this.searchOpen = detail.searchOpen === true;
            this.searchRemaining = Number(detail.searchRemaining) || 0;
            this.state = 'form';
            this.changed = false;
            this.closeAfterRequest = false;
            this.error = null;
            this.open = true;
        },
        close() {
            // Closed while the request runs: hide now, reload once the answer is in (finally), so the
            // calendar shows the result and the request is not cut off by the reload.
            if (this.loading) {
                this.closeAfterRequest = true;
                this.open = false;
                return;
            }
            // Something may have changed (also on a refusal, e.g. the search just lapsed): show the current calendar.
            if (this.changed) {
                window.location.reload();
            } else {
                this.open = false;
            }
        },
        async endSearch() {
            if (this.loading) return;
            this.loading = true;
            this.error = null;
            try {
                const res = await fetch(@js(route('player-exchange.end', ['booking' => '__BID__'])).replace('__BID__', encodeURIComponent(this.bid)), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': @js(csrf_token()),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
                let json = {};
                try { json = await res.json(); } catch { json = {}; }
                this.changed = true;
                if (res.ok && json.ok) {
                    this.searchOpen = false;
                    this.state = 'success';
                    this.$nextTick(() => document.querySelector('#cancel-dialog [data-end-done]')?.focus());
                } else {
                    this.error = json.error ?? @js(__('booking.calendar.end.failed'));
                }
            } catch {
                this.error = @js(__('booking.calendar.end.failed'));
            } finally {
                this.loading = false;
                if (this.closeAfterRequest) {
                    window.location.reload();
                }
            }
        }
     }"
     @open-cancel.window="openCancel($event.detail)">
    <x-ui.modal id="cancel-dialog" show="open" close="close()" x-bind:data-state="state">
        <x-slot:title>
            {{ __('booking.modal.cancel_heading') }}
        </x-slot:title>

        <div class="ds-modal__form" x-show="state === 'form'">
            <x-ui.summary-tiles :items="[
                ['label' => __('booking.calendar.date'), 'bind' => 'dateLabel'],
                ['label' => __('booking.calendar.time'), 'bind' => 'timeLabel'],
                ['label' => __('booking.calendar.court'), 'bind' => 'squareName'],
            ]" />

            <p class="ds-modal__message">{{ __('booking.modal.cancel_message') }}</p>
            <p class="ds-modal__message" x-show="searchOpen" x-text="hintText"></p>

            <x-ui.notice tone="problem" x-show="error"><span x-text="error"></span></x-ui.notice>

            <div class="ds-modal__actions">
                <x-ui.button variant="secondary" data-modal-initial x-bind:disabled="loading" x-on:click="open = false; $dispatch('open-edit-booking', {bid, squareName, dateLabel, timeLabel, quantity, mitspieler})">{{ __('booking.modal.edit_booking') }}</x-ui.button>
                <x-ui.button variant="secondary" data-end-search x-show="searchOpen" x-bind:disabled="loading" x-on:click="endSearch()"><span x-text="loading ? @js(__('booking.calendar.end.ending')) : @js(__('booking.calendar.end.button'))"></span></x-ui.button>
                <form method="POST" x-bind:action="@js(url('/bookings')) + '/' + bid">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger" x-bind:disabled="loading">{{ __('booking.modal.cancel_booking') }}</x-ui.button>
                </form>
                <x-ui.button variant="secondary" class="ds-modal__dismiss" x-on:click="close()">{{ __('booking.modal.cancel') }}</x-ui.button>
            </div>
        </div>

        <div class="ds-success" role="status" x-show="state === 'success'">
            <span class="ds-success__icon"><x-ui.icon name="check" :size="32" /></span>
            <p class="ds-success__title">{{ __('booking.calendar.end.done_title') }}</p>
            <p class="ds-success__text">{{ __('booking.messages.player_exchange.ended') }}</p>
            <p class="ds-success__text" x-text="dateLabel + ' · ' + timeLabel + ' · ' + squareName"></p>
        </div>

        <x-slot:footer>
            <div class="ds-modal__footer-part ds-modal__footer-part--stack ds-modal__footer-part--success-only" x-show="state === 'success'">
                <x-ui.button variant="dark" data-end-done x-on:click="close()">{{ __('booking.modal.to_calendar') }}</x-ui.button>
            </div>
        </x-slot:footer>
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
        search: false,
        searchCount: '1',
        searched: false,
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
        get canSearch() {
            if (!this.date || this.timeStart === null) return false;
            const [y, m, d] = String(this.date).split('-').map(Number);
            const start = new Date(y, m - 1, d, 0, 0, Number(this.timeStart));
            return start.getTime() > Date.now() + 3600000;
        },
        get maxSearch() {
            return Math.max(0, Number(this.quantity) - 1 - (this.mitspieler.trim() !== '' ? 1 : 0));
        },
        get searchOptions() {
            return Array.from({ length: this.maxSearch }, (_, i) => String(i + 1));
        },
        init() {
            this.$watch('maxSearch', (max) => {
                if (max < 1) this.search = false;
                else if (Number(this.searchCount) > max) this.searchCount = String(max);
            });
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
            this.search = false;
            this.searchCount = '1';
            this.searched = false;
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
                    this.searched = json.search === true;
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
                <x-ui.input name="mitspieler" required x-bind:required="!search" maxlength="255" autocomplete="off" x-model="mitspieler" x-on:input.debounce.300ms="fetchAc($event.target.value)" x-on:focus="fetchAc(mitspieler)" x-bind:placeholder="placeholder" />
                <ul class="ds-suggest" x-show="acOpen">
                    <template x-for="r in acResults" :key="r">
                        <li><button type="button" class="ds-suggest__item" x-text="r" @mousedown.prevent @click="mitspieler = r; acResults = []; acOpen = false"></button></li>
                    </template>
                </ul>
            </x-ui.field>

            <div class="ds-modal__form" x-show="canSearch && maxSearch > 0">
                {{-- No name: the server reads only search_players (sent while the box is ticked). --}}
                <x-ui.option-tile type="checkbox" value="1" :label="__('booking.modal.search_players')" x-model="search" />
                <x-ui.field for="booking-search-players" :label="__('booking.modal.search_count')" :help="__('booking.modal.search_help')" x-show="search">
                    <select name="search_players" id="booking-search-players" class="ds-select" aria-describedby="booking-search-players-help" x-model="searchCount" x-bind:disabled="!search">
                        <template x-for="n in searchOptions" :key="n"><option :value="n" x-text="n"></option></template>
                    </select>
                </x-ui.field>
            </div>

            <x-ui.notice tone="problem" x-show="error"><span x-text="error"></span></x-ui.notice>
        </form>

        <div class="ds-success" role="status" x-show="state === 'success'">
            <span class="ds-success__icon"><x-ui.icon name="check" :size="32" /></span>
            <p class="ds-success__title">{{ __('booking.modal.success_title') }}</p>
            <p class="ds-success__text" x-text="dateLabel + ' · ' + timeRange + ' · ' + courtLabel"></p>
            <p class="ds-success__text" x-show="searched">{{ __('booking.modal.search_success') }}</p>
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
     @open-event.window="openEvent($event.detail)">
    <x-ui.modal id="event-dialog" show="open" size="lg">
        <x-slot:title>
            {{ __('booking.modal.create_event') }}
        </x-slot:title>

        <p class="ds-modal__message" x-text="squareName + ' · ' + dateLabel + ' · ' + timeLabel"></p>

        <form id="event-form" method="POST" action="{{ route('admin.events.store') }}" class="ds-modal__form">
            @csrf
            <input type="hidden" name="status" value="enabled">
            <input type="hidden" name="redirect_to" value="{{ url('/') }}">

            <x-ui.field for="event-name" :label="__('booking.modal.event_name_label')">
                <x-ui.input name="name" id="event-name" required data-modal-initial :placeholder="__('booking.modal.event_name_placeholder')" />
            </x-ui.field>

            <div class="ds-modal__row">
                <x-ui.field for="event-date-start" :label="__('booking.modal.date_start')">
                    <x-ui.input type="date" name="date_start" id="event-date-start" required x-model="dateStartVal" />
                </x-ui.field>
                <x-ui.field for="event-time-start" :label="__('booking.modal.time_start')">
                    <x-ui.input type="time" name="time_start" id="event-time-start" required x-model="timeStartVal" />
                </x-ui.field>
                <x-ui.field for="event-date-end" :label="__('booking.modal.date_end')">
                    <x-ui.input type="date" name="date_end" id="event-date-end" required x-model="dateEndVal" />
                </x-ui.field>
                <x-ui.field for="event-time-end" :label="__('booking.modal.time_end')">
                    <x-ui.input type="time" name="time_end" id="event-time-end" required x-model="timeEndVal" />
                </x-ui.field>
            </div>

            <x-ui.field for="event-sid" :label="__('booking.calendar.court')">
                <x-ui.select name="sid" id="event-sid" :options="$squares->mapWithKeys(fn ($s) => [$s->sid => $s->display_name])->all()" x-model="sid" />
            </x-ui.field>

            <x-ui.field for="event-kind" :label="__('booking.admin.events.kind')">
                <x-ui.select name="kind" id="event-kind" :options="['event' => __('booking.admin.events.kind_event'), 'closure' => __('booking.admin.events.kind_closure')]" selected="event" />
            </x-ui.field>

            <x-ui.field for="event-description" :label="__('booking.modal.event_description_optional')">
                <x-ui.textarea name="description" id="event-description" :rows="2" />
            </x-ui.field>
        </form>

        <x-slot:footer>
            <div class="ds-modal__footer-part">
                <x-ui.button variant="secondary" :block="false" class="ds-modal__dismiss" x-on:click="open = false">{{ __('booking.modal.cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="event-form" :block="false">{{ __('booking.modal.save_event') }}</x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.modal>
</div>
@endcan

@auth
<div x-data="{ open: false, src: 'about:blank' }"
     @open-admin-frame.window="src = $event.detail.url; open = true"
     @message.window="if ($event.origin === window.location.origin && $event.data && $event.data.type === 'popup-close') { open = false; src = 'about:blank' }">
    <x-ui.modal id="admin-booking-modal" show="open" close="open = false; src = 'about:blank'" size="frame" :label="__('booking.modal.admin_form')">
        <iframe id="abm-iframe" class="ds-modal__frame" x-bind:src="src" title="{{ __('booking.modal.admin_form') }}"></iframe>
    </x-ui.modal>
</div>
@endauth

{{-- Player search, viewer in the player exchange (or already signed up): join or leave. --}}
@auth
<div x-data="{
        open: false,
        state: 'form',
        result: null,
        changed: false,
        closeAfterRequest: false,
        loading: false,
        error: null,
        bid: null,
        squareName: '',
        dateLabel: '',
        timeLabel: '',
        owner: '',
        remaining: 0,
        joined: false,
        optedIn: false,
        players: [],
        get ownerText() {
            return @js(__('booking.calendar.join.owner')).replace(':owner', this.owner);
        },
        get remainingText() {
            const text = this.remaining === 1 ? @js(trans_choice('booking.calendar.join.remaining', 1, ['count' => '__N__'])) : @js(trans_choice('booking.calendar.join.remaining', 2, ['count' => '__N__']));
            return text.replace('__N__', this.remaining);
        },
        get playersText() {
            return @js(__('booking.calendar.join.players')).replace(':players', this.players.join(', '));
        },
        get canJoin() {
            return this.optedIn && !this.joined;
        },
        openJoin(detail) {
            // A request from a dialog closed mid-way is still running; its reload follows.
            if (this.loading) return;
            this.bid       = detail.bid;
            this.squareName = detail.squareName;
            this.dateLabel  = detail.dateLabel;
            this.timeLabel  = detail.timeLabel;
            this.owner      = detail.owner || '';
            this.remaining  = Number(detail.remaining) || 0;
            this.joined     = detail.joined === true;
            this.optedIn    = detail.optedIn === true;
            this.players    = Array.isArray(detail.players) ? detail.players : [];
            this.state = 'form';
            this.result = null;
            this.changed = false;
            this.closeAfterRequest = false;
            this.loading = false;
            this.error = null;
            this.open = true;
        },
        close() {
            // Closed while the request runs: hide now, reload once the answer is in (finally), so the
            // calendar shows the result and the request is not cut off by the reload.
            if (this.loading) {
                this.closeAfterRequest = true;
                this.open = false;
                return;
            }
            // Something may have changed (also on a refusal, e.g. the last place just went): show the current calendar.
            if (this.changed) {
                window.location.reload();
            } else {
                this.open = false;
            }
        },
        async send(method) {
            this.loading = true;
            this.error = null;
            try {
                const res = await fetch(@js(route('player-exchange.join', ['booking' => '__BID__'])).replace('__BID__', encodeURIComponent(this.bid)), {
                    method: method,
                    headers: {
                        'X-CSRF-TOKEN': @js(csrf_token()),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
                let json = {};
                try { json = await res.json(); } catch { json = {}; }
                this.changed = true;
                if (res.ok && json.ok) {
                    this.result = method === 'POST' ? 'joined' : 'left';
                    this.state = 'success';
                    this.$nextTick(() => document.querySelector('#join-dialog [data-join-done]')?.focus());
                } else {
                    this.error = json.error ?? @js(__('booking.calendar.join.failed'));
                }
            } catch {
                this.error = @js(__('booking.calendar.join.failed'));
            } finally {
                this.loading = false;
                if (this.closeAfterRequest) {
                    window.location.reload();
                }
            }
        }
     }"
     @open-join.window="openJoin($event.detail)">
    <x-ui.modal id="join-dialog" show="open" close="close()" :eyebrow="__('booking.calendar.join.eyebrow')" x-bind:data-state="state">
        <x-slot:title>
            {{ __('booking.calendar.join.title') }}
        </x-slot:title>

        <div class="ds-modal__form" x-show="state === 'form'">
            <x-ui.summary-tiles :items="[
                ['label' => __('booking.calendar.date'), 'bind' => 'dateLabel'],
                ['label' => __('booking.calendar.time'), 'bind' => 'timeLabel'],
                ['label' => __('booking.calendar.court'), 'bind' => 'squareName'],
            ]" />

            <p class="ds-modal__message">
                <span x-show="owner" x-text="ownerText"></span><br x-show="owner">
                <strong x-text="remainingText"></strong>
            </p>
            <p class="ds-modal__message" x-show="players.length > 0" x-text="playersText"></p>

            <x-ui.notice tone="neutral" x-show="joined">{{ __('booking.calendar.join.joined_hint') }}</x-ui.notice>
            {{-- Fallback only: cells send members outside the exchange to the hint dialog (open-join-info),
                 except one already signed up, who opens this dialog as joined to leave. --}}
            <x-ui.notice tone="attention" x-show="!optedIn && !joined">{{ __('booking.calendar.join.not_opted_in') }}</x-ui.notice>
            <x-ui.notice tone="problem" x-show="error"><span x-text="error"></span></x-ui.notice>
        </div>

        <div class="ds-success" role="status" x-show="state === 'success'">
            <span class="ds-success__icon"><x-ui.icon name="check" :size="32" /></span>
            <p class="ds-success__title" x-text="result === 'joined' ? @js(__('booking.calendar.join.joined_title')) : @js(__('booking.calendar.join.left_title'))"></p>
            <p class="ds-success__text" x-text="result === 'joined' ? @js(__('booking.messages.player_exchange.joined')) : @js(__('booking.messages.player_exchange.left'))"></p>
            <p class="ds-success__text" x-text="dateLabel + ' · ' + timeLabel + ' · ' + squareName"></p>
        </div>

        <x-slot:footer>
            <div class="ds-modal__footer-part" x-show="state === 'form'">
                <x-ui.button variant="secondary" :block="false" class="ds-modal__dismiss" x-on:click="close()">{{ __('booking.modal.cancel') }}</x-ui.button>
                <x-ui.button :block="false" x-show="canJoin" data-modal-initial x-bind:disabled="loading" x-on:click="send('POST')"><span x-text="loading ? @js(__('booking.calendar.join.joining')) : @js(__('booking.calendar.join.join'))"></span></x-ui.button>
                <x-ui.button variant="danger" :block="false" x-show="joined" data-modal-initial x-bind:disabled="loading" x-on:click="send('DELETE')"><span x-text="loading ? @js(__('booking.calendar.join.leaving')) : @js(__('booking.calendar.join.leave'))"></span></x-ui.button>
                <x-ui.button :block="false" :href="route('account.edit')" x-show="!optedIn && !joined" data-modal-initial>{{ __('booking.calendar.join.account') }}</x-ui.button>
            </div>
            <div class="ds-modal__footer-part ds-modal__footer-part--stack" x-show="state === 'success'">
                <x-ui.button variant="dark" data-join-done x-on:click="close()">{{ __('booking.modal.to_calendar') }}</x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.modal>
</div>
@endauth

{{-- Player search, viewer not in the player exchange: explain how to take part. --}}
@auth
<div x-data="{
        open: false,
        squareName: '',
        dateLabel: '',
        timeLabel: '',
        openInfo(detail) {
            this.squareName = detail.squareName;
            this.dateLabel  = detail.dateLabel;
            this.timeLabel  = detail.timeLabel;
            this.open = true;
        }
     }"
     @open-join-info.window="openInfo($event.detail)">
    <x-ui.modal id="join-info-dialog" show="open" close="open = false">
        <x-slot:title>
            {{ __('booking.calendar.seeking_info.title') }}
        </x-slot:title>

        <x-ui.summary-tiles :items="[
            ['label' => __('booking.calendar.date'), 'bind' => 'dateLabel'],
            ['label' => __('booking.calendar.time'), 'bind' => 'timeLabel'],
            ['label' => __('booking.calendar.court'), 'bind' => 'squareName'],
        ]" />

        <p class="ds-modal__message">{{ __('booking.calendar.seeking_info.message') }}</p>

        <div class="ds-modal__actions">
            <x-ui.button :href="route('account.edit')" data-modal-initial>{{ __('booking.calendar.seeking_info.account') }}</x-ui.button>
            <x-ui.button variant="secondary" class="ds-modal__dismiss" x-on:click="open = false">{{ __('booking.modal.close') }}</x-ui.button>
        </div>
    </x-ui.modal>
</div>
@endauth
