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
  td:first-child { color: #555; width: 180px; }
  .footer { margin-top: 32px; font-size: 13px; color: #888; }
</style>
</head>
<body>
<div class="wrap">
  <h1>{{ __('booking.notifications.budget_alert.heading_'.$level) }}</h1>
  <p class="sub">{{ __('booking.notifications.budget_alert.body_'.$level, ['percent' => $percent]) }}</p>

  <table>
    <tr><td>{{ __('booking.notifications.budget_alert.month') }}</td><td>{{ $month }}</td></tr>
    <tr><td>{{ __('booking.notifications.budget_alert.messages') }}</td><td>{{ $messages }}</td></tr>
    <tr><td>{{ __('booking.notifications.budget_alert.spent') }}</td><td>{{ $spent }}</td></tr>
    <tr><td>{{ __('booking.notifications.budget_alert.budget') }}</td><td>{{ $budget }}</td></tr>
  </table>

  <p>{{ __('booking.notifications.budget_alert.hint_'.$level) }}</p>

  <div class="footer">
    {{ __('booking.mail.auto_footer') }}
  </div>
</div>
</body>
<!-- {{ config('booking.watermark') }} -->
</html>
