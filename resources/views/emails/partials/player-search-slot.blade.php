{{-- Court, date and time of a player search booking (plain values), plus optional extra rows [label => value]. --}}
<table>
  @if($court)
  <tr><td>{{ __('booking.mail.court') }}</td><td>{{ $court }}</td></tr>
  @endif
  @if($date)
  <tr>
    <td>{{ __('booking.mail.date') }}</td>
    <td>{{ \Carbon\Carbon::parse($date)->isoFormat('dddd, D. MMMM Y') }}</td>
  </tr>
  <tr>
    <td>{{ __('booking.mail.time') }}</td>
    <td>
      {{ $timeStart }} –
      {{ $timeEnd }} {{ __('booking.admin.common.clock_suffix') }}
    </td>
  </tr>
  @endif
  @foreach($extraRows ?? [] as $label => $value)
  <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
  @endforeach
</table>
