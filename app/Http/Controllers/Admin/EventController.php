<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Square;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class EventController extends Controller
{
    public function index(): View
    {
        $events = Event::with(['meta', 'square'])->orderByDesc('datetime_start')->get();
        return view('admin.events.index', compact('events'));
    }

    public function create(): View
    {
        return view('admin.events.create', ['squares' => Square::orderBy('priority')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEvent($request);
        $event = Event::create([
            'sid'            => $data['sid'] !== '' && $data['sid'] !== null ? (int) $data['sid'] : null,
            'status'         => $data['status'],
            'datetime_start' => $data['datetime_start'],
            'datetime_end'   => $data['datetime_end'],
            'capacity'       => null,
        ]);
        if (!empty($data['name'])) {
            $event->meta()->create(['key' => 'name', 'value' => $data['name']]);
        }
        return redirect()->route('admin.events.index')->with('success', 'Veranstaltung angelegt.');
    }

    public function edit(Event $event): View
    {
        return view('admin.events.edit', [
            'event'   => $event,
            'squares' => Square::orderBy('priority')->get(),
            'name'    => $event->meta()->where('key', 'name')->value('value'),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $data = $this->validateEvent($request);
        $event->update([
            'sid'            => $data['sid'] !== '' && $data['sid'] !== null ? (int) $data['sid'] : null,
            'status'         => $data['status'],
            'datetime_start' => $data['datetime_start'],
            'datetime_end'   => $data['datetime_end'],
        ]);
        $nameRow = $event->meta()->where('key', 'name')->first();
        if (!empty($data['name'])) {
            $nameRow ? $nameRow->update(['value' => $data['name']]) : $event->meta()->create(['key' => 'name', 'value' => $data['name']]);
        } elseif ($nameRow) {
            $nameRow->delete();
        }
        return redirect()->route('admin.events.index')->with('success', 'Veranstaltung aktualisiert.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->meta()->delete();
        $event->delete();
        return redirect()->route('admin.events.index')->with('success', 'Veranstaltung gelöscht.');
    }

    private function validateEvent(Request $request): array
    {
        return $request->validate([
            'sid'            => ['nullable'],
            'name'           => ['nullable', 'string', 'max:128'],
            'status'         => ['required', 'in:enabled,disabled'],
            'datetime_start' => ['required', 'date'],
            'datetime_end'   => ['required', 'date', 'after:datetime_start'],
        ]);
    }
}
