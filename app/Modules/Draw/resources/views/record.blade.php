{{--
    Public draw record (verification page, no login): in the tournament area shell; guests see only this record in the
    sidebar. The JSON endpoint is separate and untouched.
--}}
@extends('tournament-base::layouts.area')

@section('title', __('draw::record.title'))

@section('admin-content')
<div class="mx-auto flex w-full min-w-0 max-w-[1000px] flex-col gap-4">
@if ($draw === null)
    <h1 class="font-display text-[36px] font-bold leading-none text-ink">{{ __('draw::record.not_found') }}</h1>
@else
    <div class="flex flex-col gap-1.5">
        <h1 class="font-display text-[36px] font-bold leading-none text-ink">{{ $draw->title }}</h1>
        <p class="text-ink-2">{{ __('draw::record.lead') }}</p>
    </div>

    @if ($draw->discarded)
        <x-ui.notice tone="problem" :title="__('draw::record.discarded')">{{ $draw->discarded_reason ?: __('draw::record.no_reason') }} ({{ $draw->discarded }})</x-ui.notice>
    @endif

    <x-ui.card padding="none">
        <x-ui.table variant="kv">
            <tr>
                <th scope="row">{{ __('draw::record.status') }}</th>
                <td>
                    @if ($revealed)
                        <x-ui.badge tone="good">{{ __('draw::record.status_executed', ['date' => $draw->executed]) }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="attention">{{ __('draw::record.status_pending') }}</x-ui.badge>
                    @endif
                </td>
            </tr>
            <tr>
                <th scope="row">{{ __('draw::record.committed_at') }}</th>
                <td>{{ $draw->created }}</td>
            </tr>
            <tr>
                <th scope="row">{{ __('draw::record.commitment') }}</th>
                <td class="break-all font-mono text-[13px]">{{ $draw->commitment }}</td>
            </tr>
            <tr>
                <th scope="row">{{ __('draw::record.beacon_source') }}</th>
                <td class="break-all font-mono text-[13px]">{{ $draw->beacon_source }}</td>
            </tr>
        </x-ui.table>
    </x-ui.card>

    <div class="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(280px,1fr))]">
        <x-ui.card :title="__('draw::record.entries')">
            <p class="break-words font-mono text-[13px] text-ink-2">{{ implode(', ', $payload['entries'] ?? []) }}</p>
        </x-ui.card>
        <x-ui.card :title="__('draw::record.slots')">
            <p class="break-words font-mono text-[13px] text-ink-2">{{ implode(', ', $payload['slots'] ?? []) }}</p>
        </x-ui.card>
        <x-ui.card :title="__('draw::record.rule')">
            <p class="text-ink-2">{{ $payload['rule'] ?? '' }}</p>
        </x-ui.card>
    </div>

    @if (! $revealed)
        <x-ui.card :title="__('draw::record.pending_heading')">
            <p class="max-w-[52em] text-ink-2">{{ __('draw::record.pending_text') }}</p>
        </x-ui.card>
    @else
        <x-ui.card :title="__('draw::record.result')" padding="none">
            <x-ui.table :caption="__('draw::record.result')">
                <x-slot:head>
                    <tr><th scope="col">{{ __('draw::record.slot') }}</th><th scope="col">{{ __('draw::record.entry') }}</th></tr>
                </x-slot:head>
                @foreach ($result ?? [] as $slot => $entry)
                    <tr>
                        <td class="ds-cell--title" data-label="{{ __('draw::record.slot') }}">{{ $slot }}</td>
                        <td data-label="{{ __('draw::record.entry') }}">{{ (string) $entry }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>

        <x-ui.disclosure :summary="__('draw::record.verify_heading')" open>
            <x-ui.table variant="kv">
                <tr><th scope="row">{{ __('draw::record.server_seed') }}</th><td class="break-all font-mono text-[13px]">{{ $draw->server_seed }}</td></tr>
                <tr><th scope="row">{{ __('draw::record.beacon_value') }}</th><td class="break-all font-mono text-[13px]">{{ $draw->beacon_value }}</td></tr>
                <tr><th scope="row">{{ __('draw::record.final_seed') }}</th><td class="break-all font-mono text-[13px]">{{ $draw->final_seed }}</td></tr>
            </x-ui.table>

            <p class="mt-4 max-w-[52em] text-ink-2">{!! __('draw::record.json_hint', ['link' => '<a href="'.e(url('draw/record/'.$draw->slug.'/json')).'">'.e(__('draw::record.json_link')).'</a>']) !!}</p>

            <h2 class="mb-2 mt-4 font-display text-[22px] font-bold text-ink">{{ __('draw::record.method') }}</h2>
            <pre class="overflow-auto font-mono text-[13px] text-ink">{{ __('draw::record.method_text') }}</pre>

            <p class="mt-4 max-w-[52em] text-[13px] text-ink-2">{{ __('draw::record.beacon_note') }}</p>
        </x-ui.disclosure>
    @endif
@endif
</div>
@endsection
