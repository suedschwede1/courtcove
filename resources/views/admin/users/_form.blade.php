@csrf
<div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-[#f0ede6]">
        <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.users.section_account') }}</h2>
    </div>
    <div class="px-6 py-5 flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="uf-alias">{{ __('booking.admin.users.name') }}</label>
            <input id="uf-alias" type="text" name="alias" value="{{ old('alias', $user->alias ?? '') }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="uf-email">{{ __('booking.admin.users.email') }}</label>
            <input id="uf-email" type="email" name="email" value="{{ old('email', $user->email ?? '') }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="uf-status">{{ __('booking.admin.users.status') }}</label>
            <select id="uf-status" name="status" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
                @foreach(['placeholder' => 'status_placeholder', 'deleted' => 'status_deleted', 'blocked' => 'status_blocked', 'enabled' => 'status_enabled', 'disabled' => 'status_disabled', 'assist' => 'status_assist', 'admin' => 'status_admin'] as $val => $key)
                    <option value="{{ $val }}" @selected(old('status', $user->status ?? 'enabled') === $val)>{{ __('booking.admin.users.'.$key) }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-[#f0ede6]">
        <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.users.section_profile') }}</h2>
    </div>
    <div class="px-6 py-5 flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="uf-fn">{{ __('booking.admin.users.firstname') }}</label>
            <input id="uf-fn" type="text" name="firstname" value="{{ old('firstname', $profile['firstname'] ?? '') }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="uf-ln">{{ __('booking.admin.users.lastname') }}</label>
            <input id="uf-ln" type="text" name="lastname" value="{{ old('lastname', $profile['lastname'] ?? '') }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="uf-phone">{{ __('booking.admin.users.phone') }}</label>
            <input id="uf-phone" type="text" name="phone" value="{{ old('phone', $profile['phone'] ?? '') }}" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-[#f0ede6]">
        <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.users.section_access') }}</h2>
    </div>
    <div class="px-6 py-5 flex flex-col gap-4">
        @if(!isset($user))
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="uf-pw">{{ __('booking.admin.users.password') }}</label>
            <input id="uf-pw" type="password" name="password" class="w-full border border-[#d1cbc0] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#bf4316] focus:border-transparent">
        </div>
        @endif
        <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-[#6a6e73]" for="uf-privileges">{{ __('booking.admin.users.privileges_legend') }}</label>
            <select id="uf-privileges" name="privileges[]" multiple size="{{ count($privileges) }}" class="w-full border border-[#d1cbc0] rounded text-sm">
                @php $privLabels = __('booking.admin.users.privileges'); @endphp
                @foreach($privileges as $priv)
                    <option value="{{ $priv }}" @selected(in_array($priv, old('privileges', $granted ?? []), true))>
                        {{ $privLabels[$priv] ?? $priv }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-[#6a6e73] mt-1">{{ __('booking.admin.users.privileges_ctrl_hint') }}</p>
        </div>
    </div>
<script>
const PRIVILEGE_PRESETS = {
    admin:   ['admin.user','admin.booking','admin.event','admin.config','admin.see-menu',
               'calendar.see-past','calendar.see-data',
               'calendar.create-single-bookings','calendar.cancel-single-bookings','calendar.delete-single-bookings',
               'calendar.create-subscription-bookings','calendar.cancel-subscription-bookings','calendar.delete-subscription-bookings'],
    assist:  ['admin.booking','admin.event','admin.see-menu','calendar.see-past','calendar.see-data',
               'calendar.create-single-bookings','calendar.cancel-single-bookings','calendar.delete-single-bookings',
               'calendar.create-subscription-bookings','calendar.cancel-subscription-bookings','calendar.delete-subscription-bookings'],
    enabled:     [],
    disabled:    [],
    blocked:     [],
    deleted:     [],
    placeholder: [],
};
document.getElementById('uf-status').addEventListener('change', function () {
    const preset = PRIVILEGE_PRESETS[this.value];
    if (preset === undefined) return;
    const select = document.getElementById('uf-privileges');
    Array.from(select.options).forEach(o => o.selected = preset.includes(o.value));
});
</script>
</div>
