<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
  <h1>{{ __('booking.mail.cancelled_heading') }}</h1>
  <p class="sub">{{ __('booking.mail.cancelled_subheading') }}</p>

  <table>
    @if($square)
    <tr><td>{{ __('booking.mail.court') }}</td><td>{{ $square->display_name }}</td></tr>
    @endif
    @if($reservation)
    <tr>
      <td>{{ __('booking.mail.date') }}</td>
      <td>{{ \Carbon\Carbon::parse($reservation->date)->isoFormat('dddd, D. MMMM Y') }}</td>
    </tr>
    <tr>
      <td>{{ __('booking.mail.time') }}</td>
      <td>
        {{ substr((string) $reservation->time_start, 0, 5) }} –
        {{ substr((string) $reservation->time_end, 0, 5) }} {{ __('booking.admin.common.clock_suffix') }}
      </td>
    </tr>
    @endif
    <tr><td>{{ __('booking.mail.booking_number') }}</td><td>#{{ $booking->bid }}</td></tr>
  </table>

  @if($user)
  <p>{{ __('booking.mail.greeting_cancelled', ['name' => $user->alias]) }}<br>
  {{ __('booking.mail.cancelled_body') }}</p>
  @endif

  <div class="footer">
    {{ __('booking.mail.auto_footer') }}
  </div>
</div>
</body>
</html>

