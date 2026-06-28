@csrf
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
            @foreach(['admin','assist','enabled','disabled'] as $s)
                <option value="{{ $s }}" @selected(old('status', $user->status ?? 'enabled') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>
</div>
@if(!isset($user))
<div class="admin-form__row">
    <label class="admin-form__label" for="uf-pw">{{ __('booking.admin.users.password') }}</label>
    <div class="admin-form__field"><input id="uf-pw" type="password" name="password"></div>
</div>
@endif
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
<div class="admin-form__row">
    <span class="admin-form__label">{{ __('booking.admin.users.privileges_legend') }}</span>
    <div class="admin-form__field">
        @foreach($privileges as $priv)
            <label><input type="checkbox" name="privileges[]" value="{{ $priv }}"
                @checked(in_array($priv, old('privileges', $granted ?? []), true))> {{ $priv }}</label><br>
        @endforeach
    </div>
</div>
