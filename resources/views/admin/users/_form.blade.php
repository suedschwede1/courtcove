@csrf
<div class="ui-card">
    <div class="ui-card-header"><h2>{{ __('booking.admin.users.section_account') }}</h2></div>
    <div class="ui-card-body ui-stack">
        <p class="ui-section-label !mb-0">Konto</p>
        <div class="ui-grid-3 ui-form-panel">
            <div class="ui-field">
                <label class="ui-label" for="uf-alias">{{ __('booking.admin.users.name') }}</label>
                <input id="uf-alias" type="text" name="alias" value="{{ old('alias', $user->alias ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="uf-email">{{ __('booking.admin.users.email') }}</label>
                <input id="uf-email" type="email" name="email" value="{{ old('email', $user->email ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="uf-status">{{ __('booking.admin.users.status') }}</label>
                <select id="uf-status" name="status" class="ui-select">
                    @foreach(['placeholder' => 'status_placeholder', 'deleted' => 'status_deleted', 'blocked' => 'status_blocked', 'enabled' => 'status_enabled', 'disabled' => 'status_disabled', 'assist' => 'status_assist', 'admin' => 'status_admin'] as $val => $key)
                        <option value="{{ $val }}" @selected(old('status', $user->status ?? 'enabled') === $val)>{{ __('booking.admin.users.'.$key) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<div class="ui-card">
    <div class="ui-card-header"><h2>{{ __('booking.admin.users.section_profile') }}</h2></div>
    <div class="ui-card-body ui-stack">
        <p class="ui-section-label !mb-0">Profil</p>
        <div class="ui-grid-3 ui-form-panel">
            <div class="ui-field">
                <label class="ui-label" for="uf-fn">{{ __('booking.admin.users.firstname') }}</label>
                <input id="uf-fn" type="text" name="firstname" value="{{ old('firstname', $profile['firstname'] ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="uf-ln">{{ __('booking.admin.users.lastname') }}</label>
                <input id="uf-ln" type="text" name="lastname" value="{{ old('lastname', $profile['lastname'] ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="uf-phone">{{ __('booking.admin.users.phone') }}</label>
                <input id="uf-phone" type="text" name="phone" value="{{ old('phone', $profile['phone'] ?? '') }}" class="ui-input">
            </div>
        </div>
    </div>
</div>

<div class="ui-card">
    <div class="ui-card-header"><h2>{{ __('booking.admin.users.section_access') }}</h2></div>
    <div class="ui-card-body ui-stack">
        <p class="ui-section-label !mb-0">Zugriff</p>
        @if(!isset($user))
            <div class="ui-field max-w-md">
                <label class="ui-label" for="uf-pw">{{ __('booking.admin.users.password') }}</label>
                <input id="uf-pw" type="password" name="password" class="ui-input">
            </div>
        @endif
        <div class="ui-field">
            <label class="ui-label" for="uf-privileges">{{ __('booking.admin.users.privileges_legend') }}</label>
            <select id="uf-privileges" name="privileges[]" multiple size="{{ count($privileges) }}" class="ui-textarea ui-select min-h-[220px]">
                @php $privLabels = __('booking.admin.users.privileges'); @endphp
                @foreach($privileges as $priv)
                    <option value="{{ $priv }}" @selected(in_array($priv, old('privileges', $granted ?? []), true))>
                        {{ $privLabels[$priv] ?? $priv }}
                    </option>
                @endforeach
            </select>
            <p class="ui-help">{{ __('booking.admin.users.privileges_ctrl_hint') }}</p>
        </div>
    </div>
    <script>
const PRIVILEGE_PRESETS = {
    admin:   ['admin.user','admin.booking','admin.event','admin.config','admin.see-menu','calendar.see-past','calendar.see-data','calendar.create-single-bookings','calendar.cancel-single-bookings','calendar.delete-single-bookings','calendar.create-subscription-bookings','calendar.cancel-subscription-bookings','calendar.delete-subscription-bookings'],
    assist:  ['admin.booking','admin.event','admin.see-menu','calendar.see-past','calendar.see-data','calendar.create-single-bookings','calendar.cancel-single-bookings','calendar.delete-single-bookings','calendar.create-subscription-bookings','calendar.cancel-subscription-bookings','calendar.delete-subscription-bookings'],
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
