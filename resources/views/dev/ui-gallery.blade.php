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
    </div>
@endsection
