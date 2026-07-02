@csrf
<div class="ui-card">
    <div class="ui-card-header"><h2>{{ __('members::admin.members.section') }}</h2></div>
    <div class="ui-card-body ui-stack">
        <div class="ui-grid-3 ui-form-panel">
            <div class="ui-field">
                <label class="ui-label" for="mf-member-number">{{ __('members::admin.members.member_number') }}</label>
                <input id="mf-member-number" type="number" min="1" name="member_number" value="{{ old('member_number', $member->member_number ?? $nextNumber ?? '') }}" class="ui-input">
            </div>
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
                <label class="ui-label" for="mf-email">{{ __('members::admin.members.email') }}</label>
                <input id="mf-email" type="email" name="email" value="{{ old('email', $member->email ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="mf-phone">{{ __('members::admin.members.phone') }}</label>
                <input id="mf-phone" type="text" name="phone" value="{{ old('phone', $member->phone ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="mf-address">{{ __('members::admin.members.address') }}</label>
                <input id="mf-address" type="text" name="address" value="{{ old('address', $member->address ?? '') }}" class="ui-input">
            </div>
            <div class="ui-field">
                <label class="ui-label" for="mf-joined-at">{{ __('members::admin.members.joined_at') }}</label>
                <input id="mf-joined-at" type="date" name="joined_at" value="{{ old('joined_at', optional($member->joined_at ?? null)->format('Y-m-d') ?? now()->toDateString()) }}" class="ui-input">
            </div>
        </div>
    </div>
</div>
