<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EventBookingConflictException;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Square;
use App\Support\Calendar\EventKind;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EventService
{
    /** @return array{0:string,1:string} */
    public function combineDateTimes(array $data): array
    {
        $start = Carbon::createFromFormat('Y-m-d H:i', $data['date_start'].' '.$data['time_start'])->format('Y-m-d H:i:s');
        $end = Carbon::createFromFormat('Y-m-d H:i', $data['date_end'].' '.$data['time_end'])->format('Y-m-d H:i:s');

        return [$start, $end];
    }

    public function assertEndAfterStart(string $start, string $end): void
    {
        if ($end <= $start) {
            $message = __('booking.messages.event_end_before_start');
            throw ValidationException::withMessages([
                'time_end' => $message,
                'datetime_end' => $message,
            ]);
        }
    }

    public function create(array $data): Event
    {
        [$dtStart, $dtEnd] = $this->combineDateTimes($data);
        $this->assertEndAfterStart($dtStart, $dtEnd);

        return DB::transaction(function () use ($data, $dtStart, $dtEnd): Event {
            $sid = $this->lockCourts($data);
            $this->assertNoBookings($sid, $dtStart, $dtEnd);
            $event = Event::create([
                'sid' => $data['sid'] !== '' && $data['sid'] !== null ? (int) $data['sid'] : null,
                'status' => 'enabled',
                'datetime_start' => $dtStart,
                'datetime_end' => $dtEnd,
                'capacity' => (int) ($data['capacity'] ?? 0),
            ]);

            $this->syncMeta($event, $data);
            $kind = (string) ($data['kind'] ?? '');
            $this->storeKind($event, $kind !== '' ? $kind : EventKind::Event->value);

            return $event;
        }, 3);
    }

    public function update(Event $event, array $data): Event
    {
        [$dtStart, $dtEnd] = $this->combineDateTimes($data);
        $this->assertEndAfterStart($dtStart, $dtEnd);

        return DB::transaction(function () use ($event, $data, $dtStart, $dtEnd): Event {
            $event = Event::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();
            $sid = $this->lockCourts($data);
            if ($event->status === 'enabled') {
                $this->assertNoBookings($sid, $dtStart, $dtEnd);
            }
            $event->update([
                'sid' => $data['sid'] !== '' && $data['sid'] !== null ? (int) $data['sid'] : null,
                'datetime_start' => $dtStart,
                'datetime_end' => $dtEnd,
                'capacity' => (int) ($data['capacity'] ?? 0),
            ]);

            $this->syncMeta($event, $data);
            if ((string) ($data['kind'] ?? '') !== '') {
                $this->storeKind($event, (string) $data['kind']);
            }

            return $event;
        }, 3);
    }

    /** Lock the same court mutex used by all booking writers, in a stable order. */
    private function lockCourts(array $data): ?int
    {
        $sid = isset($data['sid']) && $data['sid'] !== '' ? (int) $data['sid'] : null;
        $courts = Square::query()->when($sid !== null, fn ($q) => $q->whereKey($sid))->orderBy('sid')->lockForUpdate()->get();
        if ($sid !== null && $courts->isEmpty()) {
            throw ValidationException::withMessages(['sid' => __('validation.exists', ['attribute' => __('booking.calendar.court')])]);
        }

        return $sid;
    }

    private function assertNoBookings(?int $sid, string $start, string $end): void
    {
        $conflicts = Reservation::query()->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.visibility', 'public')
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->when($sid !== null, fn ($q) => $q->where('bs_bookings.sid', $sid))
            ->where('bs_reservations.date', '>=', substr($start, 0, 10))
            ->where('bs_reservations.date', '<=', substr($end, 0, 10))
            ->orderBy('bs_reservations.date')->orderBy('bs_reservations.time_start')
            ->get(['bs_reservations.bid', 'bs_reservations.date', 'bs_reservations.time_start', 'bs_reservations.time_end', 'bs_bookings.sid'])
            ->filter(fn ($r): bool => $r->date.' '.$r->time_start < $end && $r->date.' '.$r->time_end > $start)
            ->map(fn ($r): array => $r->only(['bid', 'date', 'time_start', 'time_end', 'sid']))->values()->all();
        if ($conflicts !== []) {
            throw new EventBookingConflictException($conflicts);
        }
    }

    private function storeKind(Event $event, string $kind): void
    {
        $event->meta()->updateOrCreate(['key' => 'kind'], ['value' => $kind]);
    }

    private function syncMeta(Event $event, array $data): void
    {
        foreach (['name', 'description', 'notes'] as $key) {
            $row = $event->meta()->where('key', $key)->first();
            if (! empty($data[$key])) {
                $row ? $row->update(['value' => $data[$key]]) : $event->meta()->create(['key' => $key, 'value' => $data[$key]]);
            } elseif ($row) {
                $row->delete();
            }
        }
    }
}
