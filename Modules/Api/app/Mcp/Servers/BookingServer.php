<?php

declare(strict_types=1);

namespace Modules\Api\Mcp\Servers;

use Laravel\Mcp\Server;
use Modules\Api\Mcp\Tools\CreateBookingTool;
use Modules\Api\Mcp\Tools\CreateDueTool;
use Modules\Api\Mcp\Tools\CreateEventTool;
use Modules\Api\Mcp\Tools\CreateMemberTool;
use Modules\Api\Mcp\Tools\UpdateBookingTool;
use Modules\Api\Mcp\Tools\UpdateDueTool;
use Modules\Api\Mcp\Tools\UpdateEventTool;
use Modules\Api\Mcp\Tools\UpdateMemberTool;

class BookingServer extends Server
{
    protected string $name = 'Booking API';

    protected string $version = '1.0.0';

    protected string $instructions = 'Verwaltung von Platzbuchungen, Veranstaltungen, Mitgliedern und Mitgliedsbeiträgen. '
        .'Jedes Tool erfordert, dass der über Bearer-Token authentifizierte Benutzer die passende Admin-Berechtigung besitzt.';

    protected array $tools = [
        CreateBookingTool::class,
        UpdateBookingTool::class,
        CreateEventTool::class,
        UpdateEventTool::class,
        CreateMemberTool::class,
        UpdateMemberTool::class,
        CreateDueTool::class,
        UpdateDueTool::class,
    ];
}
