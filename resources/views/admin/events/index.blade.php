@extends('layouts.admin')
@section('admin-title', __('booking.admin.events.title'))
@section('admin-content')
<div class="flex flex-col gap-6">
    <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.events.title') }}</h1>

    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.common.filter') }}</h2>
        </div>
        <div class="px-6 py-5">
            <form method="GET" action="{{ route('admin.events.index') }}" class="flex flex-wrap gap-3 items-end">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.events.name') }}</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                           placeholder="{{ __('booking.admin.events.name') }} …"
                           class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.events.court') }}</label>
                    <select name="sid" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                        <option value="">{{ __('booking.admin.common.all_courts') }}</option>
                        @foreach($squares as $sq)
                            <option value="{{ $sq->sid }}" @selected((string)($filters['sid'] ?? '') === (string)$sq->sid)>{{ $sq->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.events.date_start') }}</label>
                    <input type="date" name="date" value="{{ $filters['date'] ?? '' }}"
                           class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>
                <div class="flex gap-2 items-end">
                    <button type="submit" class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-5 py-2 rounded transition-colors">{{ __('booking.admin.common.filter') }}</button>
                    <a href="{{ route('admin.events.create') }}" class="border border-[#d1cbc0] text-[#6a6e73] text-sm px-4 py-2 rounded hover:bg-[#f9f8f6] transition-colors">{{ __('booking.admin.events.new_event') }}</a>
                </div>
            </form>
        </div>
    </div>

    @if($searched)
        @if($events->isEmpty())
            <p class="text-sm text-[#6a6e73]">{{ __('booking.admin.no_results') }}</p>
        @else
            <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead><tr>
                            <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.events.name') }}</th>
                            <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.events.court') }}</th>
                            <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.events.from') }}</th>
                            <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.events.to') }}</th>
                            <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.events.status') }}</th>
                            <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap"></th>
                        </tr></thead>
                        <tbody>
                        @foreach($events as $event)
                            <tr class="hover:bg-[#fafaf9]">
                                <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $event->meta->firstWhere('key', 'name')?->value ?? '—' }}</td>
                                <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $event->square?->display_name ?? __('booking.admin.events.all_courts') }}</td>
                                <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $event->datetime_start?->format('d.m.Y H:i') }}</td>
                                <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $event->datetime_end?->format('d.m.Y H:i') }}</td>
                                <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $event->status }}</td>
                                <td class="text-sm px-4 py-3 border-b border-[#f0ede6]">
                                    <div class="flex gap-3 items-center">
                                        <a href="{{ route('admin.events.edit', $event) }}" class="border border-[#d1cbc0] text-[#6a6e73] text-sm px-4 py-2 rounded hover:bg-[#f9f8f6] transition-colors">{{ __('booking.admin.common.edit') }}</a>
                                        <form method="POST" action="{{ route('admin.events.destroy', $event) }}" class="inline"
                                              onsubmit="return confirm({{ Js::from(__('booking.admin.events.confirm_delete')) }})">
                                            @method('DELETE') @csrf
                                            <button type="submit" class="text-xs text-red-600 hover:text-red-800 hover:underline transition-colors">{{ __('booking.admin.common.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @else
        <p class="text-sm text-[#6a6e73]">{{ __('booking.admin.search_hint') }}</p>
    @endif
</div>
@endsection
