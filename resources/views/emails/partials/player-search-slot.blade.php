{{-- Court, date and time of a player search booking, plus optional extra rows [label => value]. --}}
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
  @foreach($extraRows ?? [] as $label => $value)
  <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
  @endforeach
</table>
