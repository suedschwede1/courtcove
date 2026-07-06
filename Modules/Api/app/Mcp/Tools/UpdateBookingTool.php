<?php

declare(strict_types=1);

namespace Modules\Api\Mcp\Tools;

use App\Exceptions\BookingValidationException;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Api\Transformers\BookingResource;

class UpdateBookingTool extends Tool
{
    protected string $description = 'Bearbeitet eine bestehende Platzbuchung (Zeit, Platz, Mitspieler, Status, ggf. nur eine Serien-Occurrence).';

    public function __construct(private readonly BookingService $bookingService) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'booking_id' => $schema->integer()->description('ID der Buchung (bs_bookings.bid)')->required(),
            'booked_for' => $schema->string()->description('Name des Mitglieds oder Freitext, für wen gebucht wird')->required(),
            'sid' => $schema->integer()->description('ID des Platzes (bs_squares.sid)')->required(),
            'date' => $schema->string()->description('Datum, Format YYYY-MM-DD')->required(),
            'date_end' => $schema->string()->description('Enddatum der Serie, Format YYYY-MM-DD (nur bei Wiederholung)'),
            'repeat_type' => $schema->string()->description('once|daily|every_2_days|every_3_days|every_4_days|every_5_days|every_6_days|weekly|every_2_weeks|monthly|custom')->required(),
            'time_start' => $schema->string()->description('Startzeit, Format HH:MM')->required(),
            'time_end' => $schema->string()->description('Endzeit, Format HH:MM')->required(),
            'quantity' => $schema->integer()->description('Anzahl Spieler: 2 oder 4')->required(),
            'status' => $schema->string()->description('single|subscription|cancelled (optional)'),
            'mitspieler' => $schema->string()->description('Name des Mitspielers')->required(),
            'admin_note' => $schema->string()->description('Interne Notiz (optional)'),
            'occurrence' => $schema->string()->description('Datum einer einzelnen Serien-Occurrence, die bearbeitet werden soll (optional)'),
            'edit_scope' => $schema->string()->description('single_occurrence|this_and_following (nur zusammen mit occurrence)'),
        ];
    }

    public function handle(Request $request): Response
    {
        if (! $request->user()?->can('admin.booking')) {
            return Response::error('Keine Berechtigung (admin.booking erforderlich).');
        }

        $data = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bs_bookings,bid'],
            'booked_for' => ['required', 'string', 'max:120'],
            'sid' => ['required', 'integer', 'exists:bs_squares,sid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'date_end' => ['nullable', 'date_format:Y-m-d'],
            'repeat_type' => ['required', 'in:'.implode(',', BookingService::REPEAT_KEYS)],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i'],
            'quantity' => ['required', 'integer', 'in:2,4'],
            'status' => ['nullable', 'in:single,subscription,cancelled'],
            'mitspieler' => ['required', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'occurrence' => ['nullable', 'date_format:Y-m-d'],
            'edit_scope' => ['nullable', 'in:single_occurrence,this_and_following'],
        ]);

        $booking = Booking::findOrFail($data['booking_id']);

        try {
            $owner = $this->bookingService->resolveOwner($data['booked_for']);
            [, , $occurrenceStarts, $durationSeconds] = $this->bookingService->buildOccurrenceTimeline($data);
            $booking = $this->bookingService->updateSeries(
                $booking,
                $data,
                $owner,
                $occurrenceStarts,
                $durationSeconds,
                $data['occurrence'] ?? null,
                $data['edit_scope'] ?? 'single_occurrence',
            );
        } catch (BookingValidationException $e) {
            return Response::error($e->getMessage());
        }

        return Response::json((new BookingResource($booking))->resolve());
    }
}
