<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; color: #222; font-size: 15px; line-height: 1.6; }
  .wrap { max-width: 560px; margin: 40px auto; padding: 0 20px; }
  h1 { font-size: 20px; margin-bottom: 4px; }
  .sub { color: #666; margin-bottom: 24px; }
  .btn { display: inline-block; margin-top: 20px; padding: 10px 22px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px; font-size: 15px; }
  .footer { margin-top: 32px; font-size: 13px; color: #888; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Willkommen, {{ $user->alias }}!</h1>
  <p class="sub">Ihr Konto wurde aktiviert. Sie können sich ab sofort mit Ihrer E-Mail-Adresse anmelden.</p>

  <p>
    <strong>Benutzername:</strong> {{ $user->alias }}<br>
    <strong>E-Mail:</strong> {{ $user->email }}
  </p>

  <a href="{{ url('/login') }}" class="btn">Jetzt anmelden</a>

  <div class="footer">
    Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht auf diese Nachricht.
  </div>
</div>
</body>
</html>
