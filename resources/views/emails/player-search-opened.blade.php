<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
@include('emails.partials.player-search-style')
</head>
<body>
<div class="wrap">
  <h1>{{ __('booking.mail.player_search.opened_heading') }}</h1>
  <p class="sub">{{ trans_choice('booking.mail.player_search.needed', $remaining, ['count' => $remaining]) }}</p>

  <p>{{ __('booking.mail.player_search.greeting', ['name' => $recipient->alias]) }}<br>
  {{ __('booking.mail.player_search.opened_body') }}</p>

  @include('emails.partials.player-search-slot')

  <a href="{{ $calendarUrl }}" class="btn">{{ __('booking.mail.player_search.open_calendar') }}</a>

  <div class="footer">
    {{ __('booking.mail.player_search.opened_footer') }}<br>
    <a href="{{ $unsubscribeUrl }}">{{ __('booking.mail.player_search.unsubscribe') }}</a>
  </div>
</div>
</body>
<!-- {{ config('booking.watermark') }} -->
</html>
