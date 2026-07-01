<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
  <h1>{{ __('booking.mail.test_heading') }}</h1>
  <p>{{ __('booking.mail.test_body_1') }}</p>
  <p>{{ __('booking.mail.test_body_2') }}</p>
  <div class="footer">
    {{ __('booking.mail.test_footer', ['system' => config('booking.name', config('app.name')), 'date' => now()->format('d.m.Y H:i')]) }}
  </div>
</div>
</body>
</html>
