@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.title'))
@section('admin-content')
<div class="flex flex-col gap-6">
    <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.users.title') }}</h1>

    <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap gap-3 items-end">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.search_placeholder') }}</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="{{ __('booking.admin.search_placeholder') }}"
                   class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.users.status') }}</label>
            <select name="status" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                <option value="">{{ __('booking.admin.search_status_all') }}</option>
                @foreach(['admin' => 'status_admin', 'assist' => 'status_assist', 'enabled' => 'status_enabled', 'disabled' => 'status_disabled', 'blocked' => 'status_blocked', 'deleted' => 'status_deleted', 'placeholder' => 'status_placeholder'] as $val => $key)
                <option value="{{ $val }}" @selected(($filters['status'] ?? '') === $val)>{{ __('booking.admin.users.'.$key) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-5 py-2 rounded transition-colors">{{ __('booking.admin.common.filter') }}</button>
        <a href="{{ route('admin.users.create') }}" class="border border-[#d1cbc0] text-[#6a6e73] text-sm px-4 py-2 rounded hover:bg-[#f9f8f6] transition-colors">{{ __('booking.admin.users.new_user') }}</a>
    </form>

    @if($searched)
        @if($users->isEmpty())
            <p class="text-sm text-[#6a6e73]">{{ __('booking.admin.no_results') }}</p>
        @else
            <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead><tr>
                            <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.users.name') }}</th>
                            <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.users.email') }}</th>
                            <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.users.status') }}</th>
                            <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap"></th>
                        </tr></thead>
                        <tbody>
                        @foreach($users as $u)
                            <tr class="hover:bg-[#fafaf9]">
                                <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $u->alias }}</td>
                                <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $u->email }}</td>
                                <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $u->status }}</td>
                                <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">
                                    <a href="{{ route('admin.users.edit', $u) }}" class="text-sm text-[#bf4316] hover:underline">{{ __('booking.admin.common.edit') }}</a>
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
