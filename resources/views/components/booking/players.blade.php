@props(['booking'])
{{-- The players of a booking (without the booker), members only: guests never get a name. --}}
@auth
@php($names = $booking->other_players)
@if($names !== [])
{{ implode(', ', $names) }}
@endif
@endauth
