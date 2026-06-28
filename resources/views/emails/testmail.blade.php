<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; color: #222; font-size: 15px; line-height: 1.6; }
  .wrap { max-width: 560px; margin: 40px auto; padding: 0 20px; }
  h1 { font-size: 20px; }
  .footer { margin-top: 32px; font-size: 13px; color: #888; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Testmail</h1>
  <p>Diese E-Mail bestätigt, dass der E-Mail-Versand korrekt konfiguriert ist.</p>
  <p>Wenn Sie diese Nachricht erhalten haben, funktioniert alles einwandfrei.</p>
  <div class="footer">
    Gesendet von {{ config('booking.name', config('app.name')) }} – {{ now()->format('d.m.Y H:i') }} Uhr
  </div>
</div>
</body>
</html>
