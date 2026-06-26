<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>Anmelden – ExampleClub</title></head>
<body>
<h1>Anmelden</h1>
@if($errors->any())
    <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
@endif
<form method="POST" action="/login">
    @csrf
    <label>E-Mail: <input type="email" name="email" value="{{ old('email') }}" required></label><br>
    <label>Passwort: <input type="password" name="password" required></label><br>
    <button type="submit">Anmelden</button>
</form>
</body>
</html>
