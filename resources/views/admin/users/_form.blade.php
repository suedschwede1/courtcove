@csrf
<div class="admin-form__section">
    <div class="admin-form__section-title">{{ __('booking.admin.users.section_account') }}</div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="uf-alias">{{ __('booking.admin.users.name') }}</label>
        <div class="admin-form__field"><input id="uf-alias" type="text" name="alias" value="{{ old('alias', $user->alias ?? '') }}"></div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="uf-email">{{ __('booking.admin.users.email') }}</label>
        <div class="admin-form__field"><input id="uf-email" type="email" name="email" value="{{ old('email', $user->email ?? '') }}"></div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="uf-status">{{ __('booking.admin.users.status') }}</label>
        <div class="admin-form__field">
            <select id="uf-status" name="status">
                @foreach(['admin' => 'status_admin', 'assist' => 'status_assist', 'enabled' => 'status_enabled', 'disabled' => 'status_disabled', 'blocked' => 'status_blocked'] as $val => $key)
                    <option value="{{ $val }}" @selected(old('status', $user->status ?? 'enabled') === $val)>{{ __('booking.admin.users.'.$key) }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="admin-form__section">
    <div class="admin-form__section-title">{{ __('booking.admin.users.section_profile') }}</div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="uf-fn">{{ __('booking.admin.users.firstname') }}</label>
        <div class="admin-form__field"><input id="uf-fn" type="text" name="firstname" value="{{ old('firstname', $profile['firstname'] ?? '') }}"></div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="uf-ln">{{ __('booking.admin.users.lastname') }}</label>
        <div class="admin-form__field"><input id="uf-ln" type="text" name="lastname" value="{{ old('lastname', $profile['lastname'] ?? '') }}"></div>
    </div>
    <div class="admin-form__row">
        <label class="admin-form__label" for="uf-phone">{{ __('booking.admin.users.phone') }}</label>
        <div class="admin-form__field"><input id="uf-phone" type="text" name="phone" value="{{ old('phone', $profile['phone'] ?? '') }}"></div>
    </div>
</div>

<div class="admin-form__section">
    <div class="admin-form__section-title">{{ __('booking.admin.users.section_access') }}</div>
    @if(!isset($user))
    <div class="admin-form__row">
        <label class="admin-form__label" for="uf-pw">{{ __('booking.admin.users.password') }}</label>
        <div class="admin-form__field"><input id="uf-pw" type="password" name="password"></div>
    </div>
    @endif
    <div class="admin-form__row">
        <label class="admin-form__label" for="uf-privileges">{{ __('booking.admin.users.privileges_legend') }}</label>
        <div class="admin-form__field">
            <div class="admin-privilege-presets">
                <button type="button" class="default-button" onclick="applyPrivilegePreset('mitarbeiter')">{{ __('booking.admin.users.preset_mitarbeiter') }}</button>
                <button type="button" class="default-button" onclick="applyPrivilegePreset('verwaltung')">{{ __('booking.admin.users.preset_verwaltung') }}</button>
            </div>
            <select id="uf-privileges" name="privileges[]" multiple size="{{ count($privileges) }}" class="admin-privilege-select">
                @php $privLabels = __('booking.admin.users.privileges'); @endphp
                @foreach($privileges as $priv)
                    <option value="{{ $priv }}" @selected(in_array($priv, old('privileges', $granted ?? []), true))>
                        {{ $privLabels[$priv] ?? $priv }}
                    </option>
                @endforeach
            </select>
            <p class="admin-form__note">{{ __('booking.admin.users.privileges_ctrl_hint') }}</p>
        </div>
    </div>
<script>
const PRIVILEGE_PRESETS = {
    mitarbeiter: [
        'admin.booking','admin.event','admin.see-menu',
        'calendar.see-past','calendar.see-data',
        'calendar.create-single-bookings','calendar.cancel-single-bookings','calendar.delete-single-bookings',
        'calendar.create-subscription-bookings','calendar.cancel-subscription-bookings','calendar.delete-subscription-bookings'
    ],
    verwaltung: [
        'admin.user','admin.booking','admin.config','admin.see-menu',
        'calendar.see-past','calendar.see-data'
    ]
};
function applyPrivilegePreset(name) {
    const select = document.getElementById('uf-privileges');
    const preset = PRIVILEGE_PRESETS[name] || [];
    Array.from(select.options).forEach(o => o.selected = preset.includes(o.value));
}
</script>
</div>
