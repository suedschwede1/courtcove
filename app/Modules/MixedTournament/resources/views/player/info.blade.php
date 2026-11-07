{{--
    Static help "Wie funktioniert das?" (old index/info.phtml). Layout of Turnier-Regeln / Turnier-Regeln-Mobil:
    table of contents beside one article on wide screens, one disclosure per section on phones.
    The avoid-pair section and the admin link only for global administrators (as in ZF2).
--}}
@extends('mixed::layout')

@section('title', __('mixed::info.title'))

@section('mixed-content')
@php
    $spec = [
        'formats' => ['p:p1', 'list:americano,mexicano', 'note:note1'],
        'basics' => ['p:p1'],
        'group' => ['p:p1', 'list:four,five', 'p:p2'],
        'extra' => ['p:p1', 'note:note1'],
        'mixing' => ['p:p1', 'list:initial,improve', 'p:p2'],
        'distribution' => ['p:p1', 'list:random,itn,itn_balanced,age,ladies_apart', 'note:note1'],
        'avoid' => ['p:p1'],
        'standings' => ['p:p1', 'note:note1', 'note:note2'],
        'rights' => ['lead:all', 'lead:score', 'lead:admins', 'note:note1'],
    ];
    if (! $isGlobalAdmin) {
        unset($spec['avoid']);
    }
@endphp
<div class="flex flex-col gap-2">
    <a href="{{ route('mixed.events') }}" class="inline-flex min-h-touch-min items-center gap-1 self-start text-body-s font-semibold text-ink-2 no-underline hover:text-clay-hover">
        <x-ui.icon name="chevron-left" :size="16" />{{ __('mixed::common.back_to_list') }}
    </a>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-ui.page-header :title="__('mixed::info.title')" :intro="__('mixed::info.subtitle')" />
        @if ($isGlobalAdmin)
            <x-ui.button variant="secondary" :block="false" :href="route('mixed.admin.index')">{{ __('mixed::info.admin_link') }}</x-ui.button>
        @endif
    </div>
</div>

<div class="hidden items-start gap-7 md:flex">
    <nav aria-label="{{ __('mixed::info.toc') }}" class="flex w-64 shrink-0 flex-col gap-0.5 rounded-xl border border-line bg-surface p-3.5">
        <span class="px-2 pb-1.5 text-body-s font-bold uppercase tracking-wider text-ink-3">{{ __('mixed::info.toc') }}</span>
        @foreach ($spec as $key => $blocks)
            <a href="#mixed-info-{{ $key }}" class="flex min-h-10 items-center rounded-lg px-2 font-semibold text-ink no-underline hover:bg-ground">{{ __('mixed::info.'.$key.'.title') }}</a>
        @endforeach
    </nav>

    <x-ui.card as="article" padding="lg" class="min-w-0 flex-1">
        <div class="flex flex-col gap-5 leading-relaxed">
            @foreach ($spec as $key => $blocks)
                <section id="mixed-info-{{ $key }}" class="flex flex-col gap-1.5">
                    <h2 class="font-display text-display-m text-ink">{{ __('mixed::info.'.$key.'.title') }}</h2>
                    @include('mixed::partials.info-section', ['key' => $key, 'blocks' => $blocks])
                </section>
            @endforeach
        </div>
    </x-ui.card>
</div>

<div class="flex flex-col gap-2 md:hidden">
    @foreach ($spec as $key => $blocks)
        <x-ui.disclosure :summary="__('mixed::info.'.$key.'.title')">
            <div class="flex flex-col gap-2">
                @include('mixed::partials.info-section', ['key' => $key, 'blocks' => $blocks])
            </div>
        </x-ui.disclosure>
    @endforeach
</div>
@endsection
