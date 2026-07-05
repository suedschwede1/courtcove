@csrf
<div class="ui-card">
    <div class="ui-card-header"><h2>{{ __('members::admin.members.section_person') }}</h2></div>
    <div class="ui-card-body ui-stack">
        <div class="ui-grid-3 ui-form-panel">
            <div class="ui-field">
                <label class="ui-label" for="mf-firstname">{{ __('members::admin.members.firstname') }}</label>
                <input id="mf-firstname" type="text" name="firstname" value="{{ old('firstname', $member->firstname ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="mf-lastname">{{ __('members::admin.members.lastname') }}</label>
                <input id="mf-lastname" type="text" name="lastname" value="{{ old('lastname', $member->lastname ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="mf-birthdate">{{ __('members::admin.members.birthdate') }}</label>
                <input id="mf-birthdate" type="date" name="birthdate" value="{{ old('birthdate', optional($member->birthdate ?? null)->format('Y-m-d')) }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="mf-gender">{{ __('members::admin.members.gender') }}</label>
                <select id="mf-gender" name="gender" class="ui-select">
                    <option value=""></option>
                    @foreach(\App\Modules\Members\Models\Member::GENDERS as $gender)
                        <option value="{{ $gender }}" @selected(old('gender', $member->gender ?? '') === $gender)>{{ __("members::admin.members.gender_{$gender}") }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>
<div class="ui-card">
    <div class="ui-card-header"><h2>{{ __('members::admin.members.section_contact') }}</h2></div>
    <div class="ui-card-body ui-stack">
        <div class="ui-grid-3 ui-form-panel">
            <div class="ui-field">
                <label class="ui-label" for="mf-email">{{ __('members::admin.members.email') }}</label>
                <input id="mf-email" type="email" name="email" value="{{ old('email', $member->email ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="mf-phone">{{ __('members::admin.members.phone') }}</label>
                <input id="mf-phone" type="text" name="phone" value="{{ old('phone', $member->phone ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="mf-street">{{ __('members::admin.members.street') }}</label>
                <input id="mf-street" type="text" name="street" value="{{ old('street', $member->street ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field max-w-[8rem]">
                <label class="ui-label" for="mf-zip">{{ __('members::admin.members.zip') }}</label>
                <input id="mf-zip" type="text" name="zip" value="{{ old('zip', $member->zip ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="mf-city">{{ __('members::admin.members.city') }}</label>
                <input id="mf-city" type="text" name="city" value="{{ old('city', $member->city ?? '') }}" class="ui-input">
            </div>
        </div>
    </div>
</div>
<div class="ui-card">
    <div class="ui-card-header"><h2>{{ __('members::admin.members.section_club') }}</h2></div>
    <div class="ui-card-body ui-stack">
        <div class="ui-grid-3 ui-form-panel">
            <div class="ui-field">
                <label class="ui-label" for="mf-member-number">{{ __('members::admin.members.member_number') }}</label>
                <input id="mf-member-number" type="number" min="1" name="member_number" value="{{ old('member_number', $member->member_number ?? $nextNumber ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="mf-nuliga-id">{{ __('members::admin.members.nuliga_id') }}</label>
                <input id="mf-nuliga-id" type="number" min="1" name="nuliga_id" value="{{ old('nuliga_id', $member->nuliga_id ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="mf-fee-category">{{ __('members::admin.members.fee_category') }}</label>
                <select id="mf-fee-category" name="fee_category_id" class="ui-select">
                    <option value=""></option>
                    @foreach($feeCategories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('fee_category_id', $member->fee_category_id ?? 0) === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ui-field">
                <label class="ui-label" for="mf-joined-at">{{ __('members::admin.members.joined_at') }}</label>
                <input id="mf-joined-at" type="date" name="joined_at" value="{{ old('joined_at', optional($member->joined_at ?? null)->format('Y-m-d') ?? now()->toDateString()) }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="mf-left-at">{{ __('members::admin.members.left_at') }}</label>
                <input id="mf-left-at" type="date" name="left_at" value="{{ old('left_at', optional($member->left_at ?? null)->format('Y-m-d')) }}" class="ui-input">
            </div>
        </div>
    </div>
</div>
