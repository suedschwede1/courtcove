<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; color: #222; font-size: 15px; line-height: 1.6; }
  .wrap { max-width: 560px; margin: 40px auto; padding: 0 20px; }
  h1 { font-size: 20px; margin-bottom: 4px; }
  .sub { color: #666; margin-bottom: 24px; }
  .button { display: inline-block; margin: 16px 0; padding: 10px 20px; background: #222; color: #fff; border-radius: 4px; text-decoration: none; }
  .footer { margin-top: 32px; font-size: 13px; color: #888; }
</style>
</head>
<body>
<div class="wrap">
  <h1>{{ __('booking.mail.trusted_device_heading') }}</h1>
  <p class="sub">{{ __('booking.mail.trusted_device_body', ['device' => $device]) }}</p>

  <p>{{ __('booking.mail.trusted_device_details', ['time' => $time, 'country' => $country]) }}</p>
  <p>{{ __('booking.mail.trusted_device_warning') }}</p>

  <a class="button" href="{{ $url }}">{{ __('booking.mail.trusted_device_button') }}</a>

  <div class="footer">
    {{ __('booking.mail.auto_footer') }}
  </div>
</div>
</body>
<!-- {{ config('booking.watermark') }} -->
</html>
