<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingValidationException;
use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class BookingService
{
    public function __construct(
        private readonly SquareValidator $validator,
    ) {}

    public function createSingle(
        User $user,
        Square $square,
        int $quantity,
        Carbon $dateStart,
        Carbon $dateEnd,
        array $bills = [],
        array $meta = [],
    ): Booking {
        $result = $this->validator->validate($square, $user, $quantity, $dateStart, $dateEnd);

        if (!$result->isValid()) {
            throw new BookingValidationException($result->getError());
        }

        return DB::transaction(function () use ($user, $square, $quantity, $dateStart, $dateEnd, $bills, $meta): Booking {
            $booking = Booking::create([
                'uid'            => $user->uid,
                'sid'            => $square->sid,
                'status'         => 'single',
                'status_billing' => 'pending',
                'visibility'     => 'public',
                'quantity'       => $quantity,
                'created'        => now()->format('Y-m-d H:i:s'),
            ]);

            Reservation::create([
                'bid'        => $booking->bid,
                'date'       => $dateStart->format('Y-m-d'),
                'time_start' => $dateStart->format('H:i:s'),
                'time_end'   => $dateEnd->format('H:i:s'),
            ]);

            foreach ($bills as $bill) {
                $booking->bills()->create($bill);
            }

            foreach ($meta as $entry) {
                $booking->meta()->create($entry);
            }

            return $booking->load('reservations', 'bills', 'meta');
        });
    }

    /**
     * @param array<int, string|null> $playerNames
     * @return array<array{key: string, value: string}>
     */
    public function buildPlayerMeta(array $playerNames): array
    {
        $metaEntries = [];

        foreach ([2, 3, 4] as $index) {
            $name = trim((string) ($playerNames[$index] ?? ''));
            if ($name === '') {
                continue;
            }

            $metaEntries[] = [
                'name' => 'sb-player-name-' . $index,
                'value' => $name,
            ];
        }

        if ($metaEntries === []) {
            return [];
        }

        return [[
            'key' => 'player-names',
            'value' => serialize($metaEntries),
        ]];
    }

    /** @param array<int, string|null> $playerNames */
    public function syncPlayerMeta(Booking $booking, array $playerNames): void
    {
        $meta = $this->buildPlayerMeta($playerNames);
        $row = $booking->meta()->where('key', 'player-names')->first();

        if ($meta === []) {
            if ($row) {
                $row->delete();
            }

            return;
        }

        if ($row) {
            $row->update($meta[0]);
            return;
        }

        $booking->meta()->create($meta[0]);
    }

    public function cancelSingle(Booking $booking): void
    {
        $booking->update([
            'status'         => 'cancelled',
            'status_billing' => 'cancelled',
        ]);
    }

    public function deleteSingle(Booking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            $booking->loadMissing(['meta', 'bills', 'reservations.meta']);

            foreach ($booking->reservations as $reservation) {
                $reservation->meta()->delete();
            }

            $booking->reservations()->delete();
            $booking->meta()->delete();
            $booking->bills()->delete();
            $booking->delete();
        });
    }
}
