@csrf
<label>{{ __('booking.admin.users.name') }} <input type="text" name="alias" value="{{ old('alias', $user->alias ?? '') }}"></label>
<label>{{ __('booking.admin.users.email') }} <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}"></label>
<label>{{ __('booking.admin.users.status') }}
  <select name="status">
    @foreach(['admin','assist','enabled','disabled'] as $s)
      <option value="{{ $s }}" @selected(old('status', $user->status ?? 'enabled') === $s)>{{ $s }}</option>
    @endforeach
  </select>
</label>
@if(!isset($user))<label>{{ __('booking.admin.users.password') }} <input type="password" name="password"></label>@endif
<label>{{ __('booking.admin.users.firstname') }} <input type="text" name="firstname" value="{{ old('firstname', $profile['firstname'] ?? '') }}"></label>
<label>{{ __('booking.admin.users.lastname') }} <input type="text" name="lastname" value="{{ old('lastname', $profile['lastname'] ?? '') }}"></label>
<label>{{ __('booking.admin.users.phone') }} <input type="text" name="phone" value="{{ old('phone', $profile['phone'] ?? '') }}"></label>
<fieldset><legend>{{ __('booking.admin.users.privileges_legend') }}</legend>
  @foreach($privileges as $priv)
    <label><input type="checkbox" name="privileges[]" value="{{ $priv }}"
      @checked(in_array($priv, old('privileges', $granted ?? []), true))> {{ $priv }}</label>
  @endforeach
</fieldset>
