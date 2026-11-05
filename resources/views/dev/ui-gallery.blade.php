{{-- Dev-only component gallery (local/testing). Strings here are exempt from lang keys. --}}
@extends('layouts.app')

@section('content')
    <div>
        <h1>UI gallery</h1>

        <section>
            <h2>Icons</h2>
            <div class="flex flex-wrap gap-4">
                @foreach (array_keys(\App\Support\Ui\Icons::PATHS) as $icon)
                    <div>
                        <x-ui.icon :name="$icon" :size="24" />
                        <div>{{ $icon }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section>
            <h2>Buttons</h2>
            <div class="flex flex-wrap gap-4">
                @foreach (['primary', 'secondary', 'dark', 'danger', 'text'] as $variant)
                    <x-ui.button :variant="$variant" icon="plus" :block="false">{{ $variant }}</x-ui.button>
                @endforeach
                <x-ui.button variant="secondary" :block="false" disabled>disabled</x-ui.button>
                <x-ui.button href="#" variant="primary" :block="false">link</x-ui.button>
            </div>
            <div class="flex flex-wrap gap-4">
                <x-ui.icon-button icon="chevron-left" label="Previous" />
                <x-ui.icon-button icon="chevron-right" label="Next" variant="text" />
                <span class="inline-flex bg-chrome p-2"><x-ui.icon-button icon="plus" label="Add" variant="chrome" /></span>
            </div>
            <x-ui.button>block primary</x-ui.button>
        </section>

        <section>
            <h2>Badges</h2>
            <div class="flex flex-wrap gap-4">
                @foreach (array_keys(\App\Support\Ui\BadgeTone::STATUS_MAP) as $status)
                    <x-ui.badge :status="$status" />
                @endforeach
                <x-ui.badge tone="problem">Custom</x-ui.badge>
            </div>
        </section>

        <section>
            <h2>Cards, disclosure, key figures, notices</h2>
            <div class="grid gap-4">
                <x-ui.card title="Card title" eyebrow="Eyebrow">
                    <x-slot:actions>
                        <x-ui.badge tone="good">Open</x-ui.badge>
                    </x-slot:actions>
                    Card body
                    <x-slot:footer>Card footer</x-slot:footer>
                </x-ui.card>
                <x-ui.card title="Highlight" eyebrow="Eyebrow" tone="highlight" padding="lg">Highlighted body</x-ui.card>
                <x-ui.card title="No padding" padding="none">Body</x-ui.card>
                <x-ui.disclosure summary="Disclosure">Hidden content</x-ui.disclosure>
                <div class="flex flex-wrap gap-8">
                    <x-ui.kpi label="Bookings" value="18/30" meta="this week" />
                    <x-ui.kpi label="Members" value="142" />
                </div>
                @foreach (['attention', 'problem', 'good', 'neutral'] as $tone)
                    <x-ui.notice :tone="$tone" title="Notice">{{ $tone }} message</x-ui.notice>
                @endforeach
            </div>
        </section>

        <section>
            <h2>Form fields</h2>
            <div class="grid gap-4 max-w-md">
                <x-ui.field label="Email" for="g-email" help="Used for booking confirmations only.">
                    <x-ui.input name="g-email" type="email" />
                </x-ui.field>
                <x-ui.field label="Email with error" for="g-email-err" error="Invalid address">
                    <x-ui.input name="g-email-err" type="email" value="player@example" />
                </x-ui.field>
                <x-ui.field label="Court" for="g-court">
                    <x-ui.select name="g-court" :options="['1' => 'Court A', '2' => 'Court B']" placeholder="Please choose" />
                </x-ui.field>
                <x-ui.field label="Note" for="g-note">
                    <x-ui.textarea name="g-note" :rows="3" />
                </x-ui.field>
                <x-ui.input name="g-score" variant="score" value="6" />
                <x-ui.option-group legend="Game type">
                    <x-ui.option-tile name="g-type" value="2" label="Singles" checked />
                    <x-ui.option-tile name="g-type" value="4" label="Doubles" />
                </x-ui.option-group>
            </div>
        </section>
        <section>
            <h2>Tables and pagination</h2>
            <x-ui.card padding="none">
                <x-ui.table caption="Results">
                    <x-slot:head><tr><th scope="col">Slot</th><th scope="col">Player</th><th scope="col">Score</th></tr></x-slot:head>
                    <tr><td class="ds-cell--title" data-label="Slot">Court A, 18:00</td><td data-label="Player">Player One</td><td data-label="Score">6:4</td></tr>
                    <tr class="ds-row--own"><td class="ds-cell--title" data-label="Slot">Court B, 19:00</td><td data-label="Player">You</td><td data-label="Score">3:6</td></tr>
                    <tr class="ds-row--lead"><td class="ds-cell--title" data-label="Slot">Court A, 20:00</td><td data-label="Player">Player Two</td><td data-label="Score">7:5</td></tr>
                </x-ui.table>
            </x-ui.card>
            <x-ui.card class="mt-4" padding="none">
                <x-ui.table variant="kv">
                    <tr><th scope="row">Name</th><td>Player One</td></tr>
                    <tr><th scope="row">Email</th><td>player@example.test</td></tr>
                </x-ui.table>
            </x-ui.card>
            <div class="mt-4">
                <x-ui.pagination :paginator="new \Illuminate\Pagination\LengthAwarePaginator(range(1, 10), 90, 10, 4, ['path' => '#'])" />
            </div>
        </section>
    </div>
@endsection
