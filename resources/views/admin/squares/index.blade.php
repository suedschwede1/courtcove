@extends('layouts.admin')
@section('admin-title', __('booking.admin.squares.title'))
@section('admin-content')
<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.squares.title') }}</h1>
        <a href="{{ route('admin.squares.create') }}" class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-5 py-2 rounded transition-colors">{{ __('booking.admin.squares.new_square') }}</a>
    </div>

    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.squares.title') }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr>
                    <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.squares.name') }}</th>
                    <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.squares.display_name') }}</th>
                    <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.squares.status') }}</th>
                    <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.squares.time_column') }}</th>
                    <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.squares.time_block_column') }}</th>
                    <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap"></th>
                </tr></thead>
                <tbody>
                @foreach($squares as $square)
                    <tr class="hover:bg-[#fafaf9]">
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $square->name }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $square->display_name }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ __('booking.admin.squares.status_' . $square->status->value) }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ substr((string) $square->time_start, 0, 5) }}–{{ substr((string) $square->time_end, 0, 5) }} {{ __('booking.admin.common.clock_suffix') }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ (int) round($square->time_block / 60) }} {{ __('booking.admin.common.minutes_suffix') }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6] whitespace-nowrap">
                            <a href="{{ route('admin.squares.edit', $square) }}" class="text-sm text-[#bf4316] hover:underline">{{ __('booking.admin.common.edit') }}</a>
                            <form method="POST" action="{{ route('admin.squares.destroy', $square) }}" class="inline" onsubmit="return confirm({{ Js::from(__('booking.admin.squares.confirm_delete')) }})">
                                @method('DELETE') @csrf
                                <button type="submit" class="text-xs text-red-600 hover:text-red-800 hover:underline transition-colors ml-3">{{ __('booking.admin.common.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
