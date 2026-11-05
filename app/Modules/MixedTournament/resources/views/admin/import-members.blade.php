{{--
    Member roster import (old admin/import-members.phtml), global admins only. No own mockup: the
    Admin-Mixed-Event card and form pattern.
--}}
@extends('mixed::admin.layout')

@section('title', __('mixed::admin.import.title'))

@section('mixed-admin-content')
<div class="flex flex-col gap-2">
    <a href="{{ route('mixed.admin.index') }}" class="inline-flex min-h-touch-min items-center gap-1 self-start text-body-s font-semibold text-ink-2 no-underline hover:text-clay-hover">
        <x-ui.icon name="chevron-left" :size="16" />{{ __('mixed::common.back_to_list') }}
    </a>
    <p class="text-label uppercase text-ink-3">{{ __('mixed::admin.eyebrow') }}</p>
    <h1 class="font-display text-display-l text-ink md:text-display-xl">{{ __('mixed::admin.import.title') }}</h1>
</div>

<x-ui.card :title="__('mixed::admin.import.count', ['count' => $memberCount])" class="max-w-[760px]">
    <div class="flex flex-col gap-4">
        <p class="text-body text-ink-2">{{ __('mixed::admin.import.help_format') }}</p>
        <p class="text-body text-ink-2">{{ __('mixed::admin.import.help_replace') }}</p>
        <form method="POST" action="{{ route('mixed.admin.import-members.store') }}" enctype="multipart/form-data" class="flex flex-col gap-3">
            @csrf
            <x-ui.field for="mixed-import-csv" :label="__('mixed::admin.import.field_file')">
                <x-ui.input type="file" name="csv" id="mixed-import-csv" :value="''" accept=".csv,text/csv" required />
            </x-ui.field>
            <div>
                <x-ui.button type="submit" :block="false">{{ __('mixed::admin.import.submit') }}</x-ui.button>
            </div>
        </form>
    </div>
</x-ui.card>
@endsection
