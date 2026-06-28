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
            'sid' => $request->input('sid'),
            'status' => $request->input('status', 'enabled'),
            'datetime_start' => $this->normalizeDateTime($request->input('datetime_start')),
            'datetime_end' => $this->normalizeDateTime($request->input('datetime_end')),
        ]);

        return view('admin.events.create', [
            'squares' => Square::orderBy('priority')->get(),
            'event' => $event,
            'name' => (string) $request->input('name', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEvent($request);
        $event = Event::create([
            'sid' => $data['sid'] !== '' && $data['sid'] !== null ? (int) $data['sid'] : null,
            'status' => $data['status'],
            'datetime_start' => $data['datetime_start'],
            'datetime_end' => $data['datetime_end'],
            'capacity' => null,
        ]);

        if (! empty($data['name'])) {
            $event->meta()->create(['key' => 'name', 'value' => $data['name']]);
        }

        $redirectTo = $request->string('redirect_to')->trim()->value();
        if ($redirectTo !== '') {
            return redirect()->to($redirectTo)->with('success', __('booking.messages.event_created'));
        }

        return redirect()->route('admin.events.index')->with('success', __('booking.messages.event_created'));
    }

    public function edit(Event $event): View
    {
        return view('admin.events.edit', [
            'event' => $event,
            'squares' => Square::orderBy('priority')->get(),
            'name' => $event->meta()->where('key', 'name')->value('value'),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $data = $this->validateEvent($request);
        $event->update([
            'sid' => $data['sid'] !== '' && $data['sid'] !== null ? (int) $data['sid'] : null,
            'status' => $data['status'],
            'datetime_start' => $data['datetime_start'],
            'datetime_end' => $data['datetime_end'],
        ]);
        $nameRow = $event->meta()->where('key', 'name')->first();
        if (! empty($data['name'])) {
            $nameRow ? $nameRow->update(['value' => $data['name']]) : $event->meta()->create(['key' => 'name', 'value' => $data['name']]);
        } elseif ($nameRow) {
            $nameRow->delete();
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
        return $request->validate([
            'sid' => ['nullable'],
            'name' => ['nullable', 'string', 'max:128'],
            'status' => ['required', 'in:enabled,disabled'],
            'datetime_start' => ['required', 'date'],
            'datetime_end' => ['required', 'date', 'after:datetime_start'],
        ]);
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return rescue(
            fn () => Carbon::parse($value)->format('Y-m-d H:i:s'),
            null,
            report: false,
        );
    }
}
