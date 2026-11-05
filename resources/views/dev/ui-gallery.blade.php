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
    </div>
@endsection
