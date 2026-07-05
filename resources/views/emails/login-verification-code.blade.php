<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; color: #222; font-size: 15px; line-height: 1.6; }
  .wrap { max-width: 560px; margin: 40px auto; padding: 0 20px; }
  h1 { font-size: 20px; margin-bottom: 4px; }
  .sub { color: #666; margin-bottom: 24px; }
  .code { display: inline-block; margin: 20px 0; padding: 14px 24px; background: #f4f4f4; border-radius: 4px; font-size: 28px; font-weight: bold; letter-spacing: 6px; }
  .footer { margin-top: 32px; font-size: 13px; color: #888; }
</style>
</head>
<body>
<div class="wrap">
  <h1>{{ __('booking.mail.login_verification_heading') }}</h1>
  <p class="sub">{{ __('booking.mail.login_verification_subheading') }}</p>

  <div class="code">{{ $code }}</div>

  <p>{{ __('booking.mail.login_verification_expiry') }}</p>

  <div class="footer">
    {{ __('booking.mail.auto_footer') }}
  </div>
</div>
</body>
<!-- {{ config('booking.watermark') }} -->
</html>
