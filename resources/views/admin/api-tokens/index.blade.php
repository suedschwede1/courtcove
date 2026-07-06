@extends('layouts.admin')
@section('admin-title', __('booking.admin.api_tokens.title'))
@section('admin-content')
<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.api_tokens.title') }}</h1>
    </div>

    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.api_tokens.new_token') }}</h2>
        </div>
        <form method="POST" action="{{ route('admin.api-tokens.store') }}" class="px-6 py-4 flex flex-wrap items-end gap-4">
            @csrf
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.common.member') }}</label>
                <select name="uid" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                    @foreach($users as $user)
                        <option value="{{ $user->uid }}" @selected(old('uid') == $user->uid)>{{ $user->alias }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]">{{ __('booking.admin.api_tokens.device_name') }}</label>
                <input type="text" name="device_name" value="{{ old('device_name') }}" placeholder="{{ __('booking.admin.api_tokens.device_name_placeholder') }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
            </div>
            <button type="submit" class="ui-btn ui-btn-primary">{{ __('booking.admin.api_tokens.create') }}</button>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.api_tokens.title') }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr>
                    <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.common.member') }}</th>
                    <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.api_tokens.device_name') }}</th>
                    <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.api_tokens.created_at') }}</th>
                    <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap">{{ __('booking.admin.api_tokens.last_used_at') }}</th>
                    <th class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73] px-4 py-3 border-b border-[#e0ddd7] text-left whitespace-nowrap"></th>
                </tr></thead>
                <tbody>
                @forelse($tokens as $token)
                    <tr class="hover:bg-[#fafaf9]">
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $token->tokenable?->alias ?? __('booking.admin.api_tokens.unknown_member') }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $token->name }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $token->created_at?->format('d.m.Y H:i') }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6]">{{ $token->last_used_at?->format('d.m.Y H:i') ?? __('booking.admin.api_tokens.never_used') }}</td>
                        <td class="text-sm text-[#151515] px-4 py-3 border-b border-[#f0ede6] whitespace-nowrap">
                            <form method="POST" action="{{ route('admin.api-tokens.destroy', $token) }}" class="inline" onsubmit="return confirm({{ Js::from(__('booking.admin.api_tokens.confirm_revoke')) }})">
                                @method('DELETE') @csrf
                                <button type="submit" class="text-xs text-red-600 hover:text-red-800 hover:underline transition-colors">{{ __('booking.admin.api_tokens.revoke') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-sm text-[#6a6e73] px-4 py-6 text-center">{{ __('booking.admin.api_tokens.no_tokens') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
