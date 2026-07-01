<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
  <h1>{{ __('booking.mail.activated_heading', ['name' => $user->alias]) }}</h1>
  <p class="sub">{{ __('booking.mail.activated_subheading') }}</p>

  <p>
    <strong>{{ __('booking.mail.username') }}:</strong> {{ $user->alias }}<br>
    <strong>{{ __('booking.mail.email') }}:</strong> {{ $user->email }}
  </p>

  <a href="{{ url('/login') }}" class="btn">{{ __('booking.mail.login_now') }}</a>

  <div class="footer">
    {{ __('booking.mail.auto_footer') }}
  </div>
</div>
</body>
</html>
