{{--
    Optional link of a member to a booking-system account (members.user_id). Needs: $users; optional $member,
    $label, $formId (the picker sits outside the form, e.g. in the detail side card), $help.
    An empty value means "no account": nothing requires the link.
--}}
@php
    $users = $users ?? collect();
    $selectedUser = $users->firstWhere('uid', (int) old('user_id', ($member ?? null)?->user_id ?? 0));
    $optionLabel = fn ($user) => $user->alias.($user->email ? " ({$user->email})" : '');
    $userError = $errors->first('user_id');
@endphp
<div x-data="{
    label: {{ Js::from($selectedUser ? $optionLabel($selectedUser) : '') }},
    options: {{ Js::from($users->map(fn ($user) => ['id' => $user->uid, 'label' => $optionLabel($user)])->values()) }},
    userId: {{ Js::from($selectedUser?->uid ?? '') }},
    resolve() {
        const match = this.options.find(o => o.label === this.label);
        this.userId = match ? match.id : '';
    }
}" x-on:member-unlinked.window="label = ''; userId = ''">
    <x-ui.field :label="$label ?? __('members::admin.members.user_account').' ('.__('members::admin.members.optional').')'" for="mf-user-id" :error="$userError" :help="$help ?? null">
        <input id="mf-user-id" type="text" list="mf-user-id-list" x-model="label" @input="resolve()" @change="resolve()" placeholder="{{ __('members::admin.members.search_booking_user') }}" class="ds-input" autocomplete="off"@if($userError) aria-invalid="true" aria-describedby="mf-user-id-error"@endif>
        <datalist id="mf-user-id-list">
            <template x-for="option in options" :key="option.id">
                <option :value="option.label"></option>
            </template>
        </datalist>
        <input type="hidden" name="user_id"@if($formId ?? null) form="{{ $formId }}"@endif value="{{ $selectedUser?->uid }}" :value="userId">
    </x-ui.field>
</div>
