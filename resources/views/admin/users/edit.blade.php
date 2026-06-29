@extends('layouts.admin')
@section('admin-title', __('booking.admin.users.edit_title'))
@section('admin-content')
<div class="flex flex-col gap-6">
    <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.users.edit_title') }}</h1>
    <form method="POST" action="{{ route('admin.users.update', $user) }}">
        @method('PUT')
        @include('admin.users._form', ['privileges' => $privileges, 'user' => $user, 'profile' => $profile, 'granted' => $granted])
        <div class="flex gap-3 pt-4">
            <button type="submit" class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-5 py-2 rounded transition-colors">{{ __('booking.admin.common.save') }}</button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.users.password', $user) }}">
        @csrf
        <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-[#f0ede6]">
                <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.users.reset_password') }}</h2>
            </div>
            <div class="px-6 py-5 flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="uf-new-pw">{{ __('booking.admin.users.new_password') }}</label>
                    <input id="uf-new-pw" type="password" name="password" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                </div>
                <div class="flex gap-3 items-center pt-2">
                    <button type="submit" class="border border-[#d1cbc0] text-[#6a6e73] text-sm px-4 py-2 rounded hover:bg-[#f9f8f6] transition-colors">{{ __('booking.admin.users.reset_password') }}</button>
                </div>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
          onsubmit="return confirm({{ Js::from(__('booking.admin.users.confirm_delete')) }})">
        @method('DELETE')
        @csrf
        <div class="flex gap-3 items-center pt-2">
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm px-4 py-2 rounded transition-colors">{{ __('booking.admin.users.delete_user') }}</button>
        </div>
    </form>
</div>
@endsection
