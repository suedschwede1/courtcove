<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
@include('emails.partials.player-search-style')
</head>
<body>
<div class="wrap">
  <h1>{{ __('booking.mail.player_search.joined_heading') }}</h1>
  <p class="sub">
    @if($remaining > 0)
      {{ trans_choice('booking.mail.player_search.needed', $remaining, ['count' => $remaining]) }}
    @else
      {{ __('booking.mail.player_search.joined_complete') }}
    @endif
  </p>

  @if($booking?->user)
  <p>{{ __('booking.mail.player_search.greeting', ['name' => $booking->user->alias]) }}<br>
  {{ __('booking.mail.player_search.joined_body', ['player' => $joiner->alias]) }}</p>
  @endif

  @include('emails.partials.player-search-slot', ['extraRows' => [__('booking.mail.player_search.player') => $joiner->alias]])

  <a href="{{ $calendarUrl }}" class="btn">{{ __('booking.mail.player_search.open_calendar') }}</a>

  <div class="footer">
    {{ __('booking.mail.player_search.auto_footer') }}
  </div>
</div>
</body>
<!-- {{ config('booking.watermark') }} -->
</html>
