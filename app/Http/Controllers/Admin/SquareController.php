<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Option;
use App\Models\Square;
use App\Services\BookingRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SquareController extends Controller
{
    private const DEFAULT_CAPACITY = 4;

    private const DEFAULT_CAPACITY_HETEROGENIC = 1;

    public function index(): View
    {
        $squares = Square::with('meta')->orderBy('priority')->orderBy('sid')->get();

        return view('admin.squares.index', compact('squares'));
    }

    public function create(): View
    {
        $peakLimitGlobal = Option::getValue('peak_limit.enabled', '0') === '1';

        return view('admin.squares.create', [
            'square' => null,
            'form' => $this->defaults(),
            'peakLimitGlobal' => $peakLimitGlobal,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->buildPayload($request);
        $square = Square::create(array_merge([
            'capacity' => self::DEFAULT_CAPACITY,
            'capacity_heterogenic' => self::DEFAULT_CAPACITY_HETEROGENIC,
        ], app(BookingRules::class)->columnsForNewCourt(), $payload['columns']));
        $this->applyMeta($square, $payload['meta']);

        return redirect()->route('admin.squares.index')->with('success', __('booking.messages.square_created'));
    }

    public function edit(Square $square): View
    {
        $square->load('meta');
        $peakLimitGlobal = Option::getValue('peak_limit.enabled', '0') === '1';

        return view('admin.squares.edit', [
            'square' => $square,
            'form' => $this->toForm($square),
            'peakLimitGlobal' => $peakLimitGlobal,
        ]);
    }

    public function update(Request $request, Square $square): RedirectResponse
    {
        $payload = $this->buildPayload($request);
        $square->update($payload['columns']);
        $this->applyMeta($square, $payload['meta']);

        return redirect()->route('admin.squares.index')->with('success', __('booking.messages.square_updated'));
    }

    public function destroy(Square $square): RedirectResponse
    {
        if ($square->bookings()->exists()) {
            $square->update(['status' => 'disabled']);

            return redirect()->route('admin.squares.index')
                ->with('success', __('booking.messages.square_disabled_instead_deleted'));
        }

        $square->meta()->delete();
        $square->delete();

        return redirect()->route('admin.squares.index')->with('success', __('booking.messages.square_deleted'));
    }

    /** Build form values from a square, reversing the unit conversions of buildPayload(). */
    private function toForm(Square $square): array
    {
        $publicNames = $square->getMeta('public_names') === 'true';
        $privateNames = $square->getMeta('private_names') === 'true';
        $visibility = $publicNames ? 'public' : ($privateNames ? 'private' : 'none');

        return [
            'name' => $square->name,
            'alias' => (string) $square->getMeta('alias'),
            'status' => $square->status->value,
            'readonly_message' => (string) $square->getMeta('readonly.message'),
            'priority' => $square->priority,
            'allow_notes' => (bool) $square->allow_notes,
            'name_visibility' => $visibility,
            'time_start' => substr((string) $square->time_start, 0, 5),
            'time_end' => substr((string) $square->time_end, 0, 5),
            'label_free' => (string) $square->getMeta('label.free'),
            'peak_limit_enabled' => $square->getMeta('peak_limit_enabled') === '1',
        ];
    }

    /** Validate the form and split it into bs_squares columns + bs_squares_meta values. */
    private function buildPayload(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'alias' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'in:enabled,readonly,disabled'],
            'readonly_message' => ['nullable', 'string'],
            'priority' => ['required', 'numeric'],
            'name_visibility' => ['required', 'in:none,private,public'],
            'time_start' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'time_end' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'label_free' => ['nullable', 'string', 'max:64'],
            'peak_limit_enabled' => ['nullable', 'in:0,1'],
        ]);

        $columns = [
            'name' => $data['name'],
            'status' => $data['status'],
            'priority' => (float) $data['priority'],
            'allow_notes' => $request->boolean('allow_notes') ? 1 : 0,
            'time_start' => $data['time_start'].':00',
            'time_end' => $data['time_end'].':00',
        ];

        [$privateNames, $publicNames] = match ($data['name_visibility']) {
            'public' => ['true', 'true'],
            'private' => ['true', 'false'],
            default => ['false', 'false'],
        };

        $meta = [
            'alias' => $this->nullIfBlank($data['alias'] ?? null),
            'readonly.message' => $this->nullIfBlank($data['readonly_message'] ?? null),
            'private_names' => $privateNames,
            'public_names' => $publicNames,
            'label.free' => $this->nullIfBlank($data['label_free'] ?? null),
            'peak_limit_enabled' => $request->boolean('peak_limit_enabled') ? '1' : '0',
        ];

        // While the peak limit is globally off the court switch is shown disabled and not sent: keep the stored value.
        if (Option::getValue('peak_limit.enabled', '0') !== '1') {
            unset($meta['peak_limit_enabled']);
        }

        return ['columns' => $columns, 'meta' => $meta];
    }

    /** @param array<string, string|null> $meta */
    private function applyMeta(Square $square, array $meta): void
    {
        foreach ($meta as $key => $value) {
            $square->setMeta($key, $value);
        }
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return ($value === null || $value === '') ? null : $value;
    }

    /** @return array<string, mixed> Default form values for a new square. */
    private function defaults(): array
    {
        return [
            'name' => '', 'alias' => '', 'status' => 'enabled', 'readonly_message' => '',
            'priority' => 1,
            'allow_notes' => false, 'name_visibility' => 'private',
            'time_start' => '08:00', 'time_end' => '23:00', 'label_free' => '',
            'peak_limit_enabled' => false,
        ];
    }
}
