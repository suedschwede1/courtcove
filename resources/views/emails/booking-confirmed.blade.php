<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; color: #222; font-size: 15px; line-height: 1.6; }
  .wrap { max-width: 560px; margin: 40px auto; padding: 0 20px; }
  h1 { font-size: 20px; margin-bottom: 4px; }
  .sub { color: #666; margin-bottom: 24px; }
  table { border-collapse: collapse; width: 100%; margin: 20px 0; }
  td { padding: 8px 0; border-bottom: 1px solid #eee; }
  td:first-child { color: #555; width: 140px; }
  .footer { margin-top: 32px; font-size: 13px; color: #888; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Buchungsbestätigung</h1>
  <p class="sub">Ihre Buchung wurde erfolgreich gespeichert.</p>

  <table>
    @if($square)
    <tr><td>Platz</td><td>{{ $square->display_name }}</td></tr>
    @endif
    @if($reservation)
    <tr>
      <td>Datum</td>
      <td>{{ \Carbon\Carbon::parse($reservation->date)->isoFormat('dddd, D. MMMM Y') }}</td>
    </tr>
    <tr>
      <td>Zeit</td>
      <td>
        {{ substr((string) $reservation->time_start, 0, 5) }} –
        {{ substr((string) $reservation->time_end, 0, 5) }} Uhr
      </td>
    </tr>
    @endif
    <tr><td>Spieler</td><td>{{ $booking->quantity }}</td></tr>
    <tr><td>Buchungs-Nr.</td><td>#{{ $booking->bid }}</td></tr>
  </table>

  @if($user)
  <p>Hallo {{ $user->alias }},<br>
  wir freuen uns auf Ihren Besuch!</p>
  @endif

  <div class="footer">
    Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht auf diese Nachricht.
  </div>
</div>
</body>
</html>
