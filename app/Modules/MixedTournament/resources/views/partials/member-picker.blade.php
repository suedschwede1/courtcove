{{--
    Member picker on the name field of the add-participant form. Asks the authenticated search endpoint
    (mixed.admin.member-search) from two characters on, debounced 250 ms, instead of embedding the roster.
    Results are rendered with x-text only. Choosing a member sets the name and fills gender, ITN and age
    only where they are still empty.
    Needs: $event; must sit inside an Alpine scope that defines label, gender, itn and age.
--}}
<div
    class="relative"
    x-data="{
        results: [],
        searched: false,
        timer: null,
        url: @js(route('mixed.admin.member-search', ['event' => (int) $event->id])),
        search() {
            clearTimeout(this.timer);
            const term = this.label.trim();
            if (term.length < 2) {
                this.results = [];
                this.searched = false;
                return;
            }
            this.timer = setTimeout(async () => {
                try {
                    const response = await fetch(this.url + '?q=' + encodeURIComponent(term), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (! response.ok || this.label.trim() !== term) { return; }
                    const data = await response.json();
                    this.results = Array.isArray(data.members) ? data.members : [];
                    this.searched = true;
                } catch (error) {
                    this.results = [];
                    this.searched = false;
                }
            }, 250);
        },
        meta(member) {
            const gender = member.gender === 'f' ? @js(__('mixed::common.gender_f')) : (member.gender === 'm' ? @js(__('mixed::common.gender_m')) : '');
            return [gender, member.itn !== null ? 'ITN ' + String(member.itn).replace('.', ',') : '', member.age !== null ? String(member.age) : ''].filter(Boolean).join(' · ');
        },
        choose(member) {
            this.label = member.label;
            if (this.gender === '' && member.gender) { this.gender = member.gender; }
            if (this.itn === '' && member.itn !== null) { this.itn = String(member.itn); }
            if (this.age === '' && member.age !== null) { this.age = String(member.age); }
            this.results = [];
            this.searched = false;
        },
        close() {
            this.results = [];
            this.searched = false;
        },
    }"
    x-on:keydown.escape="close()"
    x-on:click.outside="close()"
>
    <x-ui.field for="mixed-add-label" :label="__('mixed::admin.participants.field_name')">
        <x-ui.input name="label" id="mixed-add-label" :value="''" required maxlength="120" autocomplete="off" :placeholder="__('mixed::admin.participants.placeholder_name')" x-model="label" x-on:input="search()" />
    </x-ui.field>

    <ul class="absolute inset-x-0 top-full z-10 mt-1 flex max-h-72 flex-col gap-1 overflow-y-auto rounded-md border border-line bg-surface p-1 shadow-dialog" x-show="results.length > 0" x-cloak aria-label="{{ __('mixed::admin.participants.picker_results') }}">
        <template x-for="(member, index) in results" :key="index">
            <li>
                <x-ui.button variant="text" :block="false" class="w-full justify-between" x-on:click="choose(member)">
                    <span x-text="member.label"></span>
                    <span class="text-body-s font-normal text-ink-3" x-text="meta(member)"></span>
                </x-ui.button>
            </li>
        </template>
    </ul>
    <p class="mt-1 text-body-s text-ink-3" x-show="searched && results.length === 0" x-cloak>{{ __('mixed::admin.participants.picker_none') }}</p>
</div>
