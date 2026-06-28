<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Square;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class EventController extends Controller
{
    public function index(Request $request): View
    {
        $searched = $request->hasAny(['q', 'sid', 'date']);
        $events   = collect();
        $squares  = Square::orderBy('priority')->get();

        if ($searched) {
            $query = Event::with(['meta', 'square'])->orderByDesc('datetime_start');

            if ($request->filled('q')) {
                $q = '%'.$request->string('q')->trim()->value().'%';
                $query->whereHas('meta', fn ($m) => $m->where('key', 'name')->where('value', 'like', $q));
            }

            if ($request->filled('sid')) {
                $query->where('sid', (int) $request->input('sid'));
            }

            if ($request->filled('date')) {
                $date = Carbon::parse($request->input('date'));
                $query->whereDate('datetime_start', '<=', $date)->whereDate('datetime_end', '>=', $date);
            }

            $events = $query->get();
        }

        return view('admin.events.index', [
            'events'   => $events,
            'squares'  => $squares,
            'searched' => $searched,
            'filters'  => $request->only('q', 'sid', 'date'),
        ]);
    }

    public function create(Request $request): View
    {
        $event = new Event([
            'sid'      => $request->input('sid'),
            'status'   => 'enabled',
            'capacity' => 0,
        ]);

        return view('admin.events.create', array_merge(
            [
                'squares'    => Square::orderBy('priority')->get(),
                'event'      => $event,
                'name'       => '',
                'date_start' => $request->input('date_start', ''),
                'time_start' => $request->input('time_start', ''),
                'date_end'   => $request->input('date_end', ''),
                'time_end'   => $request->input('time_end', ''),
            ],
            $this->emptyMeta(),
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEvent($request);
        [$dtStart, $dtEnd] = $this->combineDateTimes($data);

        $event = Event::create([
            'sid'            => $data['sid'] !== '' && $data['sid'] !== null ? (int) $data['sid'] : null,
            'status'         => 'enabled',
            'datetime_start' => $dtStart,
            'datetime_end'   => $dtEnd,
            'capacity'       => (int) ($data['capacity'] ?? 0),
        ]);

        $this->syncMeta($event, $data);

        $redirectTo = $request->string('redirect_to')->trim()->value();
        if ($redirectTo !== '') {
            return redirect()->to($redirectTo)->with('success', __('booking.messages.event_created'));
        }

        return redirect()->route('admin.events.index')->with('success', __('booking.messages.event_created'));
    }

    public function edit(Event $event): View
    {
        $metaMap = $event->meta()->pluck('value', 'key');

        return view('admin.events.edit', [
            'event'       => $event,
            'squares'     => Square::orderBy('priority')->get(),
            'name'        => $metaMap['name'] ?? '',
            'description' => $metaMap['description'] ?? '',
            'notes'       => $metaMap['notes'] ?? '',
            'date_start'  => $event->datetime_start?->format('Y-m-d') ?? '',
            'time_start'  => $event->datetime_start?->format('H:i') ?? '',
            'date_end'    => $event->datetime_end?->format('Y-m-d') ?? '',
            'time_end'    => $event->datetime_end?->format('H:i') ?? '',
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $data = $this->validateEvent($request);
        [$dtStart, $dtEnd] = $this->combineDateTimes($data);

        $event->update([
            'sid'            => $data['sid'] !== '' && $data['sid'] !== null ? (int) $data['sid'] : null,
            'datetime_start' => $dtStart,
            'datetime_end'   => $dtEnd,
            'capacity'       => (int) ($data['capacity'] ?? 0),
        ]);

        $this->syncMeta($event, $data);

        $redirectTo = $request->string('redirect_to')->trim()->value();
        if ($redirectTo !== '') {
            return redirect()->to($redirectTo)->with('success', __('booking.messages.event_updated'));
        }

        return redirect()->route('admin.events.index')->with('success', __('booking.messages.event_updated'));
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->meta()->delete();
        $event->delete();

        return redirect()->route('admin.events.index')->with('success', __('booking.messages.event_deleted'));
    }

    private function validateEvent(Request $request): array
    {
        // Support two input shapes:
        // 1. Admin form: separate date_start/time_start/date_end/time_end fields
        // 2. Calendar popup modal: combined datetime_start/datetime_end hidden fields
        if ($request->filled('datetime_start') && ! $request->filled('date_start')) {
            $start = Carbon::parse($request->input('datetime_start'));
            $end   = Carbon::parse($request->input('datetime_end'));
            $request->merge([
                'date_start' => $start->format('Y-m-d'),
                'time_start' => $start->format('H:i'),
                'date_end'   => $end->format('Y-m-d'),
                'time_end'   => $end->format('H:i'),
            ]);
        }

        return $request->validate([
            'sid'         => ['nullable'],
            'name'        => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:4096'],
            'notes'       => ['nullable', 'string', 'max:2048'],
            'capacity'    => ['nullable', 'integer', 'min:0'],
            'date_start'  => ['required', 'date_format:Y-m-d'],
            'time_start'  => ['required', 'date_format:H:i'],
            'date_end'    => ['required', 'date_format:Y-m-d'],
            'time_end'    => ['required', 'date_format:H:i'],
        ]);
    }

    /** @return array{0:string,1:string} */
    private function combineDateTimes(array $data): array
    {
        $start = Carbon::createFromFormat('Y-m-d H:i', $data['date_start'].' '.$data['time_start'])->format('Y-m-d H:i:s');
        $end   = Carbon::createFromFormat('Y-m-d H:i', $data['date_end'].' '.$data['time_end'])->format('Y-m-d H:i:s');

        return [$start, $end];
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

    /** @return array<string,string> */
    private function emptyMeta(): array
    {
        return ['description' => '', 'notes' => ''];
    }
}
