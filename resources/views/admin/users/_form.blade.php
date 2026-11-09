@php
    // Status and permission are separate fields; the controller stores them per config users.split_role.
    $formStatus = (string) old('status', $currentStatus ?? 'enabled');
    $formRole = $formStatus === 'enabled' ? (string) old('role', $currentRole ?? 'member') : 'member';
    $statusOptions = [];
    foreach (\App\Models\User::STATUSES as $val) {
        $statusOptions[$val] = __('booking.admin.users.status_'.$val);
    }
    $roleOptions = [];
    foreach (\App\Models\User::ROLES as $val) {
        $roleOptions[$val] = __('booking.admin.users.role_'.$val);
    }
    $groups = $quoteGroups ?? [];
    $currentQuoteGroup = old('quote_group', $profile['quote_group'] ?? '');
    $privLabels = __('booking.admin.users.privileges');
    $modulePrivLabels = app(\App\Support\Modules\ModuleRegistry::class)->privileges();
@endphp
@csrf
<div class="ds-admin-stack" x-data="{ status: {{ Js::from($formStatus) }}, role: {{ Js::from($formRole) }} }" x-effect="if (status !== 'enabled') role = 'member'">
<x-ui.card :title="__('booking.admin.users.section_account')" padding="lg" class="ds-admin-form-card">
    <div class="ds-admin-form-grid">
        <x-ui.field :label="__('booking.admin.users.name')" for="uf-alias">
            <x-ui.input id="uf-alias" name="alias" :value="old('alias', $user->alias ?? '')" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.users.email')" for="uf-email">
            <x-ui.input id="uf-email" name="email" type="email" :value="old('email', $user->email ?? '')" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.users.status')" for="uf-status" :error="$errors->first('status')">
            <x-ui.select id="uf-status" name="status" :options="$statusOptions" :selected="$formStatus" x-model="status" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.users.role')" for="uf-role" :help="__('booking.admin.users.role_hint')" :error="$errors->first('role')">
            <x-ui.select id="uf-role" name="role" :options="$roleOptions" :selected="$formRole" x-model="role" x-bind:disabled="status !== 'enabled'" />
        </x-ui.field>
    </div>
</x-ui.card>

<x-ui.card :title="__('booking.admin.users.section_profile')" padding="lg" class="ds-admin-form-card">
    <div class="ds-admin-form-grid">
        <x-ui.field :label="__('booking.admin.users.firstname')" for="uf-fn">
            <x-ui.input id="uf-fn" name="firstname" :value="old('firstname', $profile['firstname'] ?? '')" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.users.lastname')" for="uf-ln">
            <x-ui.input id="uf-ln" name="lastname" :value="old('lastname', $profile['lastname'] ?? '')" />
        </x-ui.field>
        <x-ui.field :label="__('booking.admin.users.phone')" for="uf-phone">
            <x-ui.input id="uf-phone" name="phone" type="tel" :value="old('phone', $profile['phone'] ?? '')" />
        </x-ui.field>
        @if(!empty($groups) || $currentQuoteGroup !== '')
            <x-ui.field :label="__('booking.admin.users.quote_group')" for="uf-quote-group" :help="__('booking.admin.users.quote_group_hint')">
                <select id="uf-quote-group" name="quote_group" class="ds-select" aria-describedby="uf-quote-group-help">
                    <option value="">{{ __('booking.admin.users.quote_group_none') }}</option>
                    @foreach($groups as $key => $group)
                        <option value="{{ $key }}" @selected($currentQuoteGroup === $key)>{{ $group['label'] ?? $key }}</option>
                    @endforeach
                    @if($currentQuoteGroup !== '' && !array_key_exists($currentQuoteGroup, $groups))
                        <option value="{{ $currentQuoteGroup }}" selected>{{ __('booking.admin.users.quote_group_unknown', ['group' => $currentQuoteGroup]) }}</option>
                    @endif
                </select>
            </x-ui.field>
        @endif
    </div>
</x-ui.card>

{{-- On edit the card only holds the single permissions, so it follows the permission field. --}}
<x-ui.card :title="__('booking.admin.users.section_access')" padding="lg" class="ds-admin-form-card" :x-show="isset($user) ? 'role === \'assist\'' : null">
    @if(!isset($user))
        <div class="ds-admin-form-grid">
            <x-ui.field :label="__('booking.admin.users.password')" for="uf-pw">
                <x-ui.input id="uf-pw" name="password" type="password" autocomplete="new-password" />
            </x-ui.field>
        </div>
    @endif
    <x-ui.field :label="__('booking.admin.users.privileges_legend')" for="uf-privileges" :help="__('booking.admin.users.privileges_ctrl_hint')" x-show="role === 'assist'">
        <select id="uf-privileges" name="privileges[]" multiple size="{{ count($privileges) }}" class="ds-select ds-admin-privileges" aria-describedby="uf-privileges-help">
            @foreach($privileges as $priv)
                <option value="{{ $priv }}" @selected(in_array($priv, old('privileges', $granted ?? []), true))>
                    {{ isset($modulePrivLabels[$priv]) ? __($modulePrivLabels[$priv]) : ($privLabels[$priv] ?? $priv) }}
                </option>
            @endforeach
        </select>
    </x-ui.field>
</x-ui.card>
</div>
<script>
// Choosing "Assistent" on an account without single permissions preselects the usual assistant set.
const ASSIST_PRESET = ['admin.booking','admin.event','admin.see-menu','calendar.see-past','calendar.see-data','calendar.create-single-bookings','calendar.cancel-single-bookings','calendar.delete-single-bookings','calendar.create-subscription-bookings','calendar.cancel-subscription-bookings','calendar.delete-subscription-bookings'];
document.getElementById('uf-role').addEventListener('change', function () {
    const select = document.getElementById('uf-privileges');
    if (this.value !== 'assist' || select.selectedOptions.length > 0) return;
    Array.from(select.options).forEach(o => o.selected = ASSIST_PRESET.includes(o.value));
});
</script>
