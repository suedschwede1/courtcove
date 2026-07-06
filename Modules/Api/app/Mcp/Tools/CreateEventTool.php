<?php

declare(strict_types=1);

namespace Modules\Api\Mcp\Tools;

use App\Services\EventService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Api\Transformers\EventResource;

class CreateEventTool extends Tool
{
    protected string $description = 'Legt eine neue Veranstaltung an, die einen Platz (oder alle Plätze) für Buchungen sperrt.';

    public function __construct(private readonly EventService $eventService) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'sid' => $schema->integer()->description('ID des Platzes (bs_squares.sid); leer lassen für alle Plätze'),
            'name' => $schema->string()->description('Name der Veranstaltung'),
            'description' => $schema->string()->description('Beschreibung'),
            'notes' => $schema->string()->description('Interne Notizen'),
            'capacity' => $schema->integer()->description('Kapazität (0 = keine Begrenzung)'),
            'date_start' => $schema->string()->description('Startdatum, Format YYYY-MM-DD')->required(),
            'time_start' => $schema->string()->description('Startzeit, Format HH:MM')->required(),
            'date_end' => $schema->string()->description('Enddatum, Format YYYY-MM-DD')->required(),
            'time_end' => $schema->string()->description('Endzeit, Format HH:MM')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        if (! $request->user()?->can('admin.event')) {
            return Response::error('Keine Berechtigung (admin.event erforderlich).');
        }

        $data = $request->validate([
            'sid' => ['nullable'],
            'name' => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:2048'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'date_start' => ['required', 'date_format:Y-m-d'],
            'time_start' => ['required', 'date_format:H:i'],
            'date_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_start'],
            'time_end' => ['required', 'date_format:H:i'],
        ]);

        try {
            $event = $this->eventService->create($data);
        } catch (ValidationException $e) {
            return Response::error($e->getMessage());
        }

        return Response::json((new EventResource($event->load('meta')))->resolve());
    }
}
