<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Support\Calendar\EventKind;
use Carbon\Carbon;
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
    }

    public function update(Event $event, array $data): Event
    {
        [$dtStart, $dtEnd] = $this->combineDateTimes($data);
        $this->assertEndAfterStart($dtStart, $dtEnd);

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
