@extends('layouts.admin')
@section('admin-title', 'Buchung bearbeiten')
@section('admin-content')
<h1>Buchung bearbeiten</h1>
<form method="POST" action="{{ route('admin.bookings.update', $booking) }}">
    @csrf
    @method('PUT')
    @include('admin.bookings._form', ['booking' => $booking, 'users' => $users, 'squares' => $squares, 'reservation' => $reservation, 'playerNames' => $playerNames])
    <button type="submit" class="default-button">Speichern</button>
</form>

<hr>
@if($booking->status !== 'cancelled')
    <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" onsubmit="return confirm('Buchung wirklich stornieren?')">
        @csrf
        <button type="submit" class="default-button">Buchung stornieren</button>
    </form>
@endif
<form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}" onsubmit="return confirm('Buchung wirklich dauerhaft löschen?')">
    @csrf
    @method('DELETE')
    <button type="submit" class="abmelden-button default-button">Buchung dauerhaft löschen</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var quantity = document.getElementById('admin-booking-quantity');
    var player3Field = document.getElementById('admin-player3-field');
    var player4Field = document.getElementById('admin-player4-field');
    var player3Input = document.getElementById('admin-player3');
    var player4Input = document.getElementById('admin-player4');

    function syncAdminBookingFields() {
        if (!quantity) {
            return;
        }

        var isDouble = quantity.value === '4';

        if (player3Field) {
            player3Field.style.display = isDouble ? '' : 'none';
        }

        if (player4Field) {
            player4Field.style.display = isDouble ? '' : 'none';
        }

        if (player3Input) {
            player3Input.required = isDouble;
            if (!isDouble) {
                player3Input.value = '';
            }
        }

        if (player4Input) {
            player4Input.required = isDouble;
            if (!isDouble) {
                player4Input.value = '';
            }
        }
    }

    if (quantity) {
        quantity.addEventListener('change', syncAdminBookingFields);
        syncAdminBookingFields();
    }
});
</script>
@endsection
