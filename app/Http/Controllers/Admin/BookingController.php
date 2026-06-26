<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Square;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService) {}

    public function index(Request $request): View
    {
        $query = Booking::with(['user', 'square', 'reservations'])
            ->whereIn('status', Booking::ACTIVE_STATUSES);

        if ($request->filled('sid')) { $query->where('sid', (int) $request->input('sid')); }
        if ($request->filled('uid')) { $query->where('uid', (int) $request->input('uid')); }

        $bookings = $query->orderByDesc('bid')->paginate(50);
        $squares  = Square::orderBy('priority')->get();
        return view('admin.bookings.index', compact('bookings', 'squares'));
    }

    public function show(Booking $booking): View
    {
        $booking->load(['user', 'square', 'reservations', 'meta']);
        return view('admin.bookings.show', compact('booking'));
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        $this->bookingService->cancelSingle($booking);
        return redirect()->route('admin.bookings.index')->with('success', 'Buchung storniert.');
    }
}
