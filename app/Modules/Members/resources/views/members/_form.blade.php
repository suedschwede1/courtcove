@php
    use App\Modules\Members\Models\Member;
    use App\Modules\Members\Support\StatusTone;

    $genderOptions = ['' => ''];
    foreach (Member::GENDERS as $gender) {
        $genderOptions[$gender] = __("members::admin.members.gender_{$gender}");
    }
    $categoryOptions = ['' => ''] + $feeCategories->pluck('name', 'id')->all();

    $users = $users ?? collect();
    $selectedUser = $users->firstWhere('uid', (int) old('user_id', $member->user_id ?? 0));
    $userPicker = [
        'label' => $selectedUser ? $selectedUser->alias.($selectedUser->email ? " ({$selectedUser->email})" : '') : '',
        'options' => $users->map(fn ($user) => ['id' => $user->uid, 'label' => $user->alias.($user->email ? " ({$user->email})" : '')])->values(),
        'userId' => (int) old('user_id', $member->user_id ?? 0) ?: '',
    ];
    $userError = $errors->first('user_id');

    $selectedRoleIds = old('club_roles', ($member ?? null)?->clubRoles->pluck('id')->all() ?? []);
    $boardRoles = ($clubRoles ?? collect())->where('is_board', true);
    $otherRoles = ($clubRoles ?? collect())->where('is_board', false);
@endphp
@csrf
<x-ui.card :title="__('members::admin.members.section_person')">
    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.field :label="__('members::admin.members.firstname')" for="mf-firstname" :error="$errors->first('firstname')">
            <x-ui.input name="firstname" :value="old('firstname', $member->firstname ?? '')" />
        </x-ui.field>
        <x-ui.field :label="__('members::admin.members.lastname')" for="mf-lastname" :error="$errors->first('lastname')">
            <x-ui.input name="lastname" :value="old('lastname', $member->lastname ?? '')" />
        </x-ui.field>
        <x-ui.field :label="__('members::admin.members.birthdate')" for="mf-birthdate" :error="$errors->first('birthdate')">
            <x-ui.input type="date" name="birthdate" :value="old('birthdate', optional($member->birthdate ?? null)->format('Y-m-d'))" />
        </x-ui.field>
        <x-ui.field :label="__('members::admin.members.gender')" for="mf-gender" :error="$errors->first('gender')">
            <x-ui.select name="gender" :options="$genderOptions" :selected="old('gender', $member->gender ?? '')" />
        </x-ui.field>
    </div>
</x-ui.card>
<x-ui.card :title="__('members::admin.members.section_contact')">
    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.field :label="__('members::admin.members.email')" for="mf-email" :error="$errors->first('email')">
            <x-ui.input type="email" name="email" :value="old('email', $member->email ?? '')" />
        </x-ui.field>
        <x-ui.field :label="__('members::admin.members.phone')" for="mf-phone" :error="$errors->first('phone')">
            <x-ui.input name="phone" :value="old('phone', $member->phone ?? '')" />
        </x-ui.field>
        <x-ui.field :label="__('members::admin.members.street')" for="mf-street" :error="$errors->first('street')">
            <x-ui.input name="street" :value="old('street', $member->street ?? '')" />
        </x-ui.field>
        <x-ui.field class="md:max-w-32" :label="__('members::admin.members.zip')" for="mf-zip" :error="$errors->first('zip')">
            <x-ui.input name="zip" :value="old('zip', $member->zip ?? '')" />
        </x-ui.field>
        <x-ui.field :label="__('members::admin.members.city')" for="mf-city" :error="$errors->first('city')">
            <x-ui.input name="city" :value="old('city', $member->city ?? '')" />
        </x-ui.field>
    </div>
</x-ui.card>
<x-ui.card :title="__('members::admin.members.section_club')">
    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.field :label="__('members::admin.members.member_number')" for="mf-member-number" :error="$errors->first('member_number')">
            <x-ui.input type="number" min="1" name="member_number" :value="old('member_number', $member->member_number ?? $nextNumber ?? '')" />
        </x-ui.field>
        <x-ui.field :label="__('members::admin.members.nuliga_id')" for="mf-nuliga-id" :error="$errors->first('nuliga_id')">
            <x-ui.input type="number" min="1" name="nuliga_id" :value="old('nuliga_id', $member->nuliga_id ?? '')" />
        </x-ui.field>
        <div x-data="{
            label: {{ Js::from($userPicker['label']) }},
            options: {{ Js::from($userPicker['options']) }},
            userId: {{ Js::from($userPicker['userId']) }},
            resolve() {
                const match = this.options.find(o => o.label === this.label);
                this.userId = match ? match.id : '';
            }
        }">
            <x-ui.field :label="__('members::admin.members.booking_user_short')" for="mf-user-id" :error="$userError">
                <input id="mf-user-id" type="text" list="mf-user-id-list" x-model="label" @input="resolve()" @change="resolve()" placeholder="{{ __('members::admin.members.search_booking_user') }}" class="ds-input" autocomplete="off"@if($userError) aria-invalid="true" aria-describedby="mf-user-id-error"@endif>
                <datalist id="mf-user-id-list">
                    <template x-for="option in options" :key="option.id">
                        <option :value="option.label"></option>
                    </template>
                </datalist>
                <input type="hidden" name="user_id" :value="userId">
            </x-ui.field>
        </div>
        <x-ui.field :label="__('members::admin.members.fee_category')" for="mf-fee-category" :error="$errors->first('fee_category_id')">
            <x-ui.select name="fee_category_id" :options="$categoryOptions" :selected="(string) ((int) old('fee_category_id', $member->fee_category_id ?? 0) ?: '')" />
        </x-ui.field>
        <x-ui.field :label="__('members::admin.members.joined_at')" for="mf-joined-at" :error="$errors->first('joined_at')">
            <x-ui.input type="date" name="joined_at" :value="old('joined_at', optional($member->joined_at ?? null)->format('Y-m-d') ?? now()->toDateString())" />
        </x-ui.field>
        <x-ui.field :label="__('members::admin.members.left_at')" for="mf-left-at" :error="$errors->first('left_at')">
            <x-ui.input type="date" name="left_at" :value="old('left_at', optional($member->left_at ?? null)->format('Y-m-d'))" />
        </x-ui.field>
        @if($member ?? null)
            <div class="ds-field">
                <span class="ds-field__label">{{ __('members::admin.members.status') }}</span>
                <div>
                    <x-ui.badge :tone="StatusTone::member($member->status)">{{ __('members::admin.members.status_'.($member->status === 'ausgetreten' ? 'departed' : ($member->status === 'ruhend' ? 'ruhend' : 'active'))) }}</x-ui.badge>
                </div>
            </div>
        @endif
    </div>
    @if(($clubRoles ?? collect())->isNotEmpty())
        <div class="ds-field mt-4">
            <span class="ds-field__label">{{ __('members::admin.members.club_roles') }}</span>
            <div class="grid gap-4 md:grid-cols-3">
                @foreach([['members::admin.members.club_roles_board', $boardRoles], ['members::admin.members.club_roles_other', $otherRoles]] as [$headingKey, $roles])
                    @if($roles->isNotEmpty())
                        <div>
                            <p class="text-label uppercase text-ink-3 mb-1">{{ __($headingKey) }}</p>
                            @foreach($roles as $role)
                                <label class="flex items-center gap-2 text-ink">
                                    <input type="checkbox" name="club_roles[]" value="{{ $role->id }}" @checked(in_array($role->id, $selectedRoleIds))>
                                    {{ $role->name }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</x-ui.card>
@if($member ?? null)
    <x-ui.card :title="__('members::admin.members.section_notiz')">
        <x-ui.field :label="__('members::admin.members.notiz')" for="mf-notiz" :error="$errors->first('notiz')">
            <x-ui.textarea name="notiz" rows="4" :value="old('notiz', $member->notiz ?? '')" />
        </x-ui.field>
    </x-ui.card>
@endif
