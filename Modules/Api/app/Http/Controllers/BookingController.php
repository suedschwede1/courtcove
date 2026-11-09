<?php

declare(strict_types=1);

namespace Modules\Api\Http\Controllers;

use App\Exceptions\BookingValidationException;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Modules\Api\Http\Requests\BookingRequest;
use Modules\Api\Transformers\BookingResource;

class BookingController extends ApiController
{
    public function __construct(private readonly BookingService $bookingService) {}

    public function store(BookingRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $owner = $this->bookingService->resolveOwner($data['booked_for']);
            [, , $occurrenceStarts, $durationSeconds] = $this->bookingService->buildOccurrenceTimeline($data);
            $booking = $this->bookingService->createSeries($data, $owner, $occurrenceStarts, $durationSeconds);
        } catch (BookingValidationException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(new BookingResource($booking), 201);
    }

    public function update(BookingRequest $request, Booking $booking): JsonResponse
    {
        $data = $request->validated();
        $occurrenceDate = $data['occurrence'] ?? null;
        $scope = $data['edit_scope'] ?? 'single_occurrence';

        try {
            $owner = $this->bookingService->resolveOwner($data['booked_for'], $booking);
            [, , $occurrenceStarts, $durationSeconds] = $this->bookingService->buildUpdateTimeline($booking, $data, $occurrenceDate, $scope);
            $booking = $this->bookingService->updateSeries($booking, $data, $owner, $occurrenceStarts, $durationSeconds, $occurrenceDate, $scope);
        } catch (BookingValidationException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(new BookingResource($booking));
    }
}
