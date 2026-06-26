@csrf
<label>Name <input type="text" name="alias" value="{{ old('alias', $user->alias ?? '') }}"></label>
<label>E-Mail <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}"></label>
<label>Status
  <select name="status">
    @foreach(['admin','assist','enabled','disabled'] as $s)
      <option value="{{ $s }}" @selected(old('status', $user->status ?? 'enabled') === $s)>{{ $s }}</option>
    @endforeach
  </select>
</label>
@if(!isset($user))<label>Passwort <input type="password" name="password"></label>@endif
<label>Vorname <input type="text" name="firstname" value="{{ old('firstname', $profile['firstname'] ?? '') }}"></label>
<label>Nachname <input type="text" name="lastname" value="{{ old('lastname', $profile['lastname'] ?? '') }}"></label>
<label>Telefon <input type="text" name="phone" value="{{ old('phone', $profile['phone'] ?? '') }}"></label>
<fieldset><legend>Rechte (für assist)</legend>
  @foreach($privileges as $priv)
    <label><input type="checkbox" name="privileges[]" value="{{ $priv }}"
      @checked(in_array($priv, old('privileges', $granted ?? []), true))> {{ $priv }}</label>
  @endforeach
</fieldset>
