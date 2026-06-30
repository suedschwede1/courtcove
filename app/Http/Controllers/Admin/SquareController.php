<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Option;
use App\Models\Square;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        ], $payload['columns']));
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
            'capacity_ask_names' => (string) $square->getMeta('capacity-ask-names', ''),
            'allow_notes' => (bool) $square->allow_notes,
            'name_visibility' => $visibility,
            'time_start' => substr((string) $square->time_start, 0, 5),
            'time_end' => substr((string) $square->time_end, 0, 5),
            'time_block' => (int) round($square->time_block / 60),
            'time_block_bookable' => (int) round($square->time_block_bookable / 60),
            'pseudo_time_block_bookable' => $square->getMeta('pseudo-time-block-bookable') === 'true',
            'time_block_bookable_max' => (int) round(((int) $square->time_block_bookable_max) / 60),
            'min_range_book' => (int) round($square->min_range_book / 60),
            'range_book' => (int) round(((int) $square->range_book) / 86400),
            'short_booking_window' => (int) round($square->short_booking_window / 60),
            'max_active_bookings' => (int) $square->max_active_bookings,
            'range_cancel' => round(((int) $square->range_cancel) / 3600, 2),
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
            'capacity_ask_names' => ['nullable', Rule::in(Square::ASK_NAMES_OPTIONS)],
            'name_visibility' => ['required', 'in:none,private,public'],
            'time_start' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'time_end' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'time_block' => ['required', 'integer', 'min:0'],
            'time_block_bookable' => ['required', 'integer', 'min:0'],
            'time_block_bookable_max' => ['required', 'integer', 'min:0'],
            'min_range_book' => ['required', 'integer', 'min:0'],
            'range_book' => ['required', 'integer', 'min:0'],
            'short_booking_window' => ['required', 'integer', 'min:0'],
            'max_active_bookings' => ['required', 'integer', 'min:0'],
            'range_cancel' => ['required', 'numeric', 'min:0'],
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
            'time_block' => (int) $data['time_block'] * 60,
            'time_block_bookable' => (int) $data['time_block_bookable'] * 60,
            'time_block_bookable_max' => (int) $data['time_block_bookable_max'] * 60,
            'min_range_book' => (int) $data['min_range_book'] * 60,
            'range_book' => (int) $data['range_book'] * 86400,
            'max_active_bookings' => (int) $data['max_active_bookings'],
            'range_cancel' => (int) round(((float) $data['range_cancel']) * 3600),
        ];

        [$privateNames, $publicNames] = match ($data['name_visibility']) {
            'public' => ['true', 'true'],
            'private' => ['true', 'false'],
            default => ['false', 'false'],
        };

        $shortBookingWindowSeconds = (int) $data['short_booking_window'] * 60;

        $meta = [
            'alias' => $this->nullIfBlank($data['alias'] ?? null),
            'readonly.message' => $this->nullIfBlank($data['readonly_message'] ?? null),
            'capacity-ask-names' => $this->nullIfBlank($data['capacity_ask_names'] ?? null),
            'private_names' => $privateNames,
            'public_names' => $publicNames,
            'pseudo-time-block-bookable' => $request->boolean('pseudo_time_block_bookable') ? 'true' : 'false',
            'short-booking-window' => $shortBookingWindowSeconds > 0 ? (string) $shortBookingWindowSeconds : null,
            'label.free' => $this->nullIfBlank($data['label_free'] ?? null),
            'peak_limit_enabled' => $request->boolean('peak_limit_enabled') ? '1' : '0',
        ];

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
            'priority' => 1, 'capacity_ask_names' => '',
            'allow_notes' => false, 'name_visibility' => 'private',
            'time_start' => '08:00', 'time_end' => '23:00', 'time_block' => 60,
            'time_block_bookable' => 30, 'pseudo_time_block_bookable' => false,
            'time_block_bookable_max' => 180, 'min_range_book' => 0, 'range_book' => 56,
            'short_booking_window' => 0, 'max_active_bookings' => 0, 'range_cancel' => 24, 'label_free' => '',
            'peak_limit_enabled' => false,
        ];
    }
}
