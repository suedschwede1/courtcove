{{--
    Member autocomplete for the add-player form: asks the server for matches on demand (debounced, from two
    characters) instead of embedding every member. A pick fills uid, label and email of the surrounding scope.
    Needs: $ladder; must sit inside an Alpine scope that defines uid, label and email.
--}}
@php
    use App\Modules\Pyramid\Support\PyramidLinks;
@endphp
<div
    class="flex flex-col gap-2"
    x-data="{
        q: '',
        results: [],
        searched: false,
        timer: null,
        url: @js(PyramidLinks::adminMembers((int) $ladder->id)),
        search() {
            clearTimeout(this.timer);
            const term = this.q.trim();
            if (term.length < 2) {
                this.results = [];
                this.searched = false;
                return;
            }
            this.timer = setTimeout(async () => {
                try {
                    const response = await fetch(this.url + '?q=' + encodeURIComponent(term), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (!response.ok || this.q.trim() !== term) { return; }
                    this.results = await response.json();
                    this.searched = true;
                } catch (error) {
                    this.results = [];
                    this.searched = false;
                }
            }, 250);
        },
    }"
>
    <x-ui.field for="pyramid-add-picker" :label="__('pyramid::admin.ladder.add.picker_label')">
        <x-ui.input type="search" name="member-search" id="pyramid-add-picker" :value="''" autocomplete="off" :placeholder="__('pyramid::admin.ladder.add.picker_placeholder')" x-model="q" x-on:input="search()" />
    </x-ui.field>

    <ul class="flex flex-col gap-1" x-show="results.length > 0" x-cloak aria-label="{{ __('pyramid::admin.ladder.add.picker_results') }}">
        <template x-for="member in results" :key="member.uid">
            <li>
                <x-ui.button variant="secondary" :block="false" class="w-full justify-between" x-on:click="uid = member.uid; label = member.alias; email = member.email ?? ''; q = ''; results = []; searched = false">
                    <span x-text="member.alias"></span>
                    <span class="text-body-s font-normal text-ink-3" x-text="member.email ?? ''"></span>
                </x-ui.button>
            </li>
        </template>
    </ul>
    <p class="text-body-s text-ink-3" x-show="searched && results.length === 0" x-cloak>{{ __('pyramid::admin.ladder.add.picker_none') }}</p>
</div>
