<?php

declare(strict_types=1);

namespace Modules\Api\Http\Controllers;

use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Modules\Api\Http\Requests\EventRequest;
use Modules\Api\Transformers\EventResource;

class EventController extends ApiController
{
    public function __construct(private readonly EventService $eventService) {}

    public function store(EventRequest $request): JsonResponse
    {
        $event = $this->eventService->create($request->validated());

        return $this->success(new EventResource($event->load('meta')), 201);
    }

    public function update(EventRequest $request, Event $event): JsonResponse
    {
        $event = $this->eventService->update($event, $request->validated());

        return $this->success(new EventResource($event->load('meta')));
    }
}
