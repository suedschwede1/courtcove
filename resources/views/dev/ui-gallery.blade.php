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
    </div>
@endsection
