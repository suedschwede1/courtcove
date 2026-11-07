<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
@include('emails.partials.player-search-style')
</head>
<body>
<div class="wrap">
  <h1>{{ __('booking.mail.player_search.cancelled_heading') }}</h1>
  <p class="sub">{{ __('booking.mail.player_search.cancelled_subheading') }}</p>

  <p>{{ __('booking.mail.player_search.greeting', ['name' => $player->alias]) }}<br>
  {{ __('booking.mail.player_search.cancelled_body') }}</p>

  @include('emails.partials.player-search-slot')

  <div class="footer">
    {{ __('booking.mail.player_search.auto_footer') }}
  </div>
</div>
</body>
<!-- {{ config('booking.watermark') }} -->
</html>
